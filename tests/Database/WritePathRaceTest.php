<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Database;

use Closure;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\SeoKitBundle\Controller\Admin\SeoSiteSettingsController;
use Nowo\SeoKitBundle\Controller\Api\SeoSurfaceApiController;
use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\EventSubscriber\SeoKitConflictListener;
use Nowo\SeoKitBundle\Model\SeoSurfaceData;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Nowo\SeoKitBundle\Service\SeoSurfaceManager;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

use function json_decode;

/**
 * REQ-RUNTIME-003: the bundle's hot write paths with two real connections. A is another request (a second worker); B
 * is the request under test, writing through the bundle's private entity manager, as in the default configuration.
 * The interleavings are deterministic: A writes from B's preFlush (between B's read and B's insert), or holds a row
 * lock while B writes (B's session fails after LOCK_TIMEOUT seconds).
 */
final class WritePathRaceTest extends DatabaseTestCase
{
    public function testSurfaceInsertedByAnotherRequestFirstIsUpdatedNotDuplicated(): void
    {
        $registry = $this->bundleRegistry();
        $manager  = new SeoSurfaceManager(new SeoSurfaceRepository($registry));
        // A inserts and commits (page:home, en) after B read "no row" and before B's insert.
        $this->beforeFirstFlush($registry, function (): void {
            $this->insertOnA((new SeoSurface())->setSurfaceKey('page:home')->setLocale('en')->setMetaTitle('From A'));
        });

        self::assertTrue($manager->save(new SeoSurfaceData('page:home', 'en', 'From B', metaDescription: 'B text')));

        self::assertSame(1, $this->rows('nowo_seo_surface'), 'One row per (key, locale): the second insert must not duplicate it.');
        $row = $this->a->fetchAssociative('SELECT meta_title, meta_description FROM nowo_seo_surface');
        self::assertSame(['meta_title' => 'From B', 'meta_description' => 'B text'], $row, 'Last writer wins, as for an update.');
        // The next write of the same worker works (the private manager closed by the refused insert was replaced).
        self::assertTrue($manager->save(new SeoSurfaceData('page:home', 'en', 'Again')));
        self::assertSame('Again', $this->a->fetchOne('SELECT meta_title FROM nowo_seo_surface'));
    }

    public function testRepositoryUpsertReturnsTheStoredRow(): void
    {
        $registry = $this->bundleRegistry();
        $this->beforeFirstFlush($registry, function (): void {
            $this->insertOnA((new SeoSurface())->setSurfaceKey('page:about')->setLocale('es'));
        });

        $stored = (new SeoSurfaceRepository($registry))->upsert((new SeoSurface())->setSurfaceKey('page:about')->setLocale('es')->setMetaTitle('B'));

        $id = (int) $this->a->fetchOne('SELECT id FROM nowo_seo_surface');
        self::assertSame($id, $stored->getId());
        self::assertSame('B', $stored->getMetaTitle());
        self::assertSame(1, $this->rows('nowo_seo_surface'));
    }

    public function testSettingsSeedRaceReturnsTheWinnerRow(): void
    {
        $registry = $this->bundleRegistry();
        $this->beforeFirstFlush($registry, function (): void {
            $this->insertOnA((new SeoSiteSettings())->setSiteName('Seeded by A'));
        });

        $settings = (new SeoSiteSettingsRepository($registry, environmentSiteName: 'Seeded by B'))->getOrCreate();

        self::assertSame('Seeded by A', $settings->getSiteName());
        self::assertSame(1, $this->rows('nowo_seo_site_settings'));
    }

    public function testDuplicateTranslationAnswersADomain409WithoutAConflictCode(): void
    {
        $registry   = $this->bundleRegistry();
        $repository = new SeoSiteSettingsRepository($registry);
        $settings   = $repository->getOrCreate();
        $settings->translationFor('fr')->setDefaultTitle('B');
        // A adds the same missing translation first.
        $this->beforeFirstFlush($registry, function (): void {
            $manager  = $this->manager($this->a);
            $settings = $manager->find(SeoSiteSettings::class, SeoSiteSettings::SINGLETON_ID);
            self::assertInstanceOf(SeoSiteSettings::class, $settings);
            $settings->translationFor('fr')->setDefaultTitle('A');
            $manager->flush();
        });

        $failure = $this->failureOf(static fn () => $repository->save($settings));
        self::assertInstanceOf(UniqueConstraintViolationException::class, $failure);

        $answer = $this->answer(SeoSiteSettingsController::class . '::edit', $failure, $this->request(SeoSiteSettingsController::class . '::edit'));
        self::assertInstanceOf(HttpExceptionInterface::class, $answer);
        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(SeoKitConflictListener::DUPLICATE_KEY, $answer->getHeaders()[SeoKitConflictListener::ERROR_CODE_HEADER] ?? null);
        self::assertArrayNotHasKey(ConflictResponsePolicy::CODE_HEADER, $answer->getHeaders(), 'A unique violation is not a lock conflict.');
        self::assertSame('A', $this->a->fetchOne("SELECT default_title FROM nowo_seo_site_settings_translation WHERE locale = 'fr'"));
    }

    public function testRowLockedByAnotherRequestAnswersConflict001AndTheWorkerRecovers(): void
    {
        $registry = $this->bundleRegistry();
        $surfaces = new SeoSurfaceRepository($registry);
        $surfaces->upsert((new SeoSurface())->setSurfaceKey('page:home')->setLocale('en')->setMetaTitle('Before'));
        $request = $this->request(SeoSurfaceApiController::class . '::post');

        $this->a->beginTransaction();
        $this->a->executeStatement("UPDATE nowo_seo_surface SET meta_title = 'A' WHERE surface_key = 'page:home'");
        $surface = $surfaces->findOneByKeyAndLocale('page:home', 'en');
        self::assertInstanceOf(SeoSurface::class, $surface);
        $failure = $this->failureOf(static fn (): SeoSurface => $surfaces->upsert($surface->setMetaTitle('B')));
        $this->a->rollBack();

        $answer = $this->answer(SeoSurfaceApiController::class . '::post', $failure, $request);
        self::assertInstanceOf(JsonResponse::class, $answer);
        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(ConflictCode::RETRY_SAFE, $answer->headers->get(ConflictResponsePolicy::CODE_HEADER));
        self::assertSame('1', $answer->headers->get('Retry-After'));
        self::assertSame(['ok' => false, 'errors' => [SeoKitConflictListener::RETRY_SAFE_MESSAGE], 'code' => ConflictCode::RETRY_SAFE], json_decode((string) $answer->getContent(), true));
        self::assertSame('Before', $this->a->fetchOne('SELECT meta_title FROM nowo_seo_surface'), 'Nothing of B was committed.');

        // The retry (the next request of the same worker) succeeds.
        $retry = $surfaces->findOneByKeyAndLocale('page:home', 'en');
        self::assertInstanceOf(SeoSurface::class, $retry);
        $surfaces->upsert($retry->setMetaTitle('B'));
        self::assertSame('B', $this->a->fetchOne('SELECT meta_title FROM nowo_seo_surface'));
    }

    public function testLockConflictAfterACommitOfTheSameRequestAnswersConflict002(): void
    {
        $registry   = $this->bundleRegistry();
        $repository = new SeoSiteSettingsRepository($registry, environmentSiteName: 'Seeded');
        // The settings page: the request seeds the settings row (a commit), then its save waits on A's lock.
        $request  = $this->request(SeoSiteSettingsController::class . '::edit');
        $settings = $repository->getOrCreate();

        $this->a->beginTransaction();
        $this->a->executeStatement("UPDATE nowo_seo_site_settings SET site_name = 'A'");
        $failure = $this->failureOf(static fn () => $repository->save($settings->setSiteName('B')));
        $this->a->rollBack();

        $answer = $this->answer(SeoSiteSettingsController::class . '::edit', $failure, $request);
        self::assertInstanceOf(HttpExceptionInterface::class, $answer);
        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(ConflictCode::COMMITTED, $answer->getHeaders()[ConflictResponsePolicy::CODE_HEADER] ?? null);
        self::assertArrayNotHasKey('Retry-After', $answer->getHeaders(), 'CONFLICT_002 never invites a blind retry.');
        self::assertSame('Seeded', $this->a->fetchOne('SELECT site_name FROM nowo_seo_site_settings'));
    }

    /**
     * Runs $write once, from the first preFlush of the bundle's manager on B (between B's read and B's write).
     *
     * @param Closure(): void $write
     */
    private function beforeFirstFlush(ManagerRegistry $registry, Closure $write): void
    {
        $manager = $registry->getManagerForClass(SeoSurface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        $listener = new class($write) {
            private bool $done = false;

            /** @param Closure(): void $write */
            public function __construct(private readonly Closure $write)
            {
            }

            public function preFlush(): void
            {
                if (!$this->done) {
                    $this->done = true;
                    ($this->write)();
                }
            }
        };
        $manager->getEventManager()->addEventListener([Events::preFlush], $listener);
    }

    /**
     * Inserts and commits $entity on A, through the ORM (the id generator of the platform and doctrine/dbal version).
     */
    private function insertOnA(object $entity): void
    {
        $manager = $this->manager($this->a);
        $manager->persist($entity);
        $manager->flush();
    }

    /**
     * A main request of $controller, with the bundle's commit mark taken (kernel.request).
     */
    private function request(string $controller): Request
    {
        $request = new Request();
        $request->attributes->set('_controller', $controller);
        $this->listener()->onRequest(new RequestEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST));

        return $request;
    }

    /**
     * The bundle's answer to $failure on $request: the response it set, or the throwable it put in place.
     */
    private function answer(string $controller, Throwable $failure, Request $request): JsonResponse|HttpExceptionInterface|null
    {
        self::assertSame($controller, $request->attributes->get('_controller'));
        $event = new ExceptionEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST, $failure);
        $this->listener()->onException($event);
        $response = $event->getResponse();
        if ($response instanceof JsonResponse) {
            return $response;
        }
        $throwable = $event->getThrowable();

        return $throwable instanceof HttpExceptionInterface ? $throwable : null;
    }

    private function listener(): SeoKitConflictListener
    {
        return new SeoKitConflictListener(new ConflictResponsePolicy(), $this->ledger);
    }

    private function kernel(): HttpKernelInterface
    {
        return $this->createStub(HttpKernelInterface::class);
    }

    /**
     * @param Closure(): mixed $write
     */
    private function failureOf(Closure $write): Throwable
    {
        try {
            $write();
        } catch (Throwable $failure) {
            return $failure;
        }

        self::fail('The write must fail.');
    }
}
