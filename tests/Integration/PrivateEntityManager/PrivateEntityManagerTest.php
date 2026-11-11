<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\PrivateEntityManager;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Nowo\SeoKitBundle\Doctrine\SeoKitEntityManager;
use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Model\SeoSurfaceData;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Nowo\SeoKitBundle\Service\SeoSurfaceManagerInterface;
use Nowo\SeoKitBundle\Service\SeoSurfaceStoreInterface;
use Nowo\SeoKitBundle\Tests\Integration\Ddd\Domain\HostArticle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernel;
use Throwable;

use function gc_collect_cycles;
use function memory_get_usage;
use function sprintf;

/**
 * REQ-ARCH-001 rule 6 for the standard (zero-config) host, 1.24.0: the SeoKit writes go through the bundle's private
 * entity manager, never through the application's default manager. One connection, one default manager mapping the
 * host domain and the SeoKit entities.
 */
final class PrivateEntityManagerTest extends KernelTestCase
{
    private EntityManagerInterface $host;

    private Connection $connection;

    private SeoKitEntityManager $private;

    protected static function getKernelClass(): string
    {
        return PrivateEntityManagerKernel::class;
    }

    protected function setUp(): void
    {
        // debug false: the DBAL debug middleware keeps every query until kernel.reset, which a worker test never runs.
        self::bootKernel(['debug' => false]);
        $doctrine = self::getContainer()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $doctrine);
        $host = $doctrine->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $host);
        $this->host       = $host;
        $this->connection = $host->getConnection();
        (new SchemaTool($host))->createSchema($host->getMetadataFactory()->getAllMetadata());
        $private = self::getContainer()->get('test.pem.private');
        self::assertInstanceOf(SeoKitEntityManager::class, $private);
        $this->private = $private;
    }

    public function testEveryWritePathLeavesTheHostPendingChangesUntouched(): void
    {
        $article = new HostArticle('pending');
        $this->host->persist($article);
        $this->host->getFilters()->enable('tenant')->setParameter('tenant', 'acme');

        $store    = $this->store();
        $manager  = $this->manager();
        $surfaces = $this->service('test.pem.surfaces', SeoSurfaceRepository::class);
        $settings = $this->service('test.pem.settings', SeoSiteSettingsRepository::class);

        $paths = [
            'store save (insert)'    => static fn (): bool => $store->save(new SeoSurfaceData('page:a', 'en', 'A')),
            'store save (update)'    => static fn (): bool => $store->save(new SeoSurfaceData('page:a', 'en', 'A2')),
            'store clear'            => static fn (): bool => $store->clear('page:a', 'en'),
            'manager getOrCreate'    => static fn (): SeoSurface => $manager->getOrCreate('page:b', 'en'),
            'manager saveOrClear'    => static fn (): bool => $manager->saveOrClear($manager->getOrCreate('page:b', 'en')->setMetaTitle('B')),
            'manager saveOrClear rm' => static fn (): bool => $manager->saveOrClear($manager->getOrCreate('page:b', 'en')->setMetaTitle(null)),
            'repository save'        => static fn () => $surfaces->save((new SeoSurface())->setSurfaceKey('page:c')->setLocale('en')->setMetaTitle('C')),
            'repository remove'      => static fn () => $surfaces->remove($surfaces->getOrCreate('page:c', 'en')),
            'settings getOrCreate'   => $settings->getOrCreate(...),
            'settings save'          => static fn () => $settings->save($settings->getOrCreate()->setDefaultRobots('noindex')),
        ];
        foreach ($paths as $path => $write) {
            $write();
            self::assertTrue($this->host->getUnitOfWork()->isScheduledForInsert($article), $path . ': the host insert is still pending.');
            self::assertSame(0, $this->rows('host_article'), $path . ': nothing of the host was flushed.');
            self::assertTrue($this->host->isOpen());
        }
        self::assertSame('noindex', (string) $this->connection->fetchOne(sprintf('SELECT %s FROM nowo_seo_site_settings', $this->host->getClassMetadata(SeoSiteSettings::class)->getColumnName('defaultRobots'))));
        self::assertSame(0, $this->rows('nowo_seo_surface'));

        $this->host->flush();
        self::assertSame(1, $this->rows('host_article'));
    }

    public function testHostTransactionRollbackThenTheSameSaveWritesTheRow(): void
    {
        $store = $this->store();
        $store->save(new SeoSurfaceData('page:home', 'en', 'A'));

        $this->connection->beginTransaction();
        $store->save(new SeoSurfaceData('page:home', 'en', 'B'));
        $this->connection->rollBack();

        self::assertSame('A', $this->title('page:home'), 'The host rolled the bundle write back.');
        self::assertTrue($this->private->isStale());
        $store->save(new SeoSurfaceData('page:home', 'en', 'B'));
        self::assertSame('B', $this->title('page:home'), 'The repeated save is not lost to a stale identity map.');
        self::assertFalse($this->private->isStale());
    }

    public function testLoadEditSavepointRollbackSaveWritesTheRow(): void
    {
        $manager = $this->manager();
        $manager->saveOrClear($manager->getOrCreate('page:home', 'en')->setMetaTitle('A'));

        // Load and edit, then the bundle write runs inside a host savepoint that is rolled back.
        $surface = $manager->find('page:home', 'en');
        self::assertInstanceOf(SeoSurface::class, $surface);
        $surface->setMetaTitle('B');
        $this->connection->beginTransaction();
        $this->connection->beginTransaction(); // savepoint
        $manager->saveOrClear($surface);
        $this->connection->rollBack(); // to the savepoint
        $this->connection->commit();
        self::assertSame('A', $this->title('page:home'));

        $again = $manager->find('page:home', 'en');
        self::assertInstanceOf(SeoSurface::class, $again);
        self::assertSame('A', $again->getMetaTitle(), 'Loaded again from the database, not from the stale identity map.');
        $manager->saveOrClear($again->setMetaTitle('B'));
        self::assertSame('B', $this->title('page:home'));

        // Same through the store, with a savepoint only.
        $store = $this->store();
        $this->connection->beginTransaction();
        $this->connection->beginTransaction();
        $store->save(new SeoSurfaceData('page:home', 'en', 'C'));
        $this->connection->rollBack();
        $this->connection->commit();
        self::assertSame('B', $this->title('page:home'));
        $store->save(new SeoSurfaceData('page:home', 'en', 'C'));
        self::assertSame('C', $this->title('page:home'));
    }

    /** Audit MQ-L-L1: a stale manager with scheduled work is kept, so a pending edit is not dropped. */
    public function testStaleManagerWithPendingWorkIsKept(): void
    {
        $manager = $this->manager();
        $manager->saveOrClear($manager->getOrCreate('page:home', 'en')->setMetaTitle('A'));
        $surface = $manager->find('page:home', 'en');
        self::assertInstanceOf(SeoSurface::class, $surface);
        $surface->setMetaTitle('Edited');

        $this->connection->beginTransaction();
        $this->connection->rollBack(); // an unrelated host rollback
        self::assertTrue($this->private->isStale());

        self::assertNull($this->store()->findData('page:other', 'en'));
        self::assertTrue($this->private->live()->contains($surface), 'The stale manager with a pending update is kept.');
        $manager->saveOrClear($surface);
        self::assertSame('Edited', $this->title('page:home'));
    }

    public function testFailedBundleFlushClosesOnlyThePrivateManagerAndItIsRecreated(): void
    {
        $article = new HostArticle('pending');
        $this->host->persist($article);
        $doctrine = self::getContainer()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $doctrine);
        $surfaces = $this->service('test.pem.surfaces', SeoSurfaceRepository::class);
        $surfaces->save((new SeoSurface())->setSurfaceKey('page:dup')->setLocale('en')->setMetaTitle('1'));
        $closed = $this->private->live();

        try {
            $surfaces->save((new SeoSurface())->setSurfaceKey('page:dup')->setLocale('en')->setMetaTitle('2'));
            self::fail('A duplicate surface must fail.');
        } catch (Throwable) {
        }

        self::assertFalse($closed->isOpen(), 'The failed flush closed the private manager.');
        self::assertSame($this->host, $doctrine->getManager(), 'The host manager was never reset.');
        self::assertTrue($this->host->isOpen());
        self::assertTrue($this->host->getUnitOfWork()->isScheduledForInsert($article));
        $this->store()->save(new SeoSurfaceData('page:after', 'en', 'Works'));
        self::assertNotSame($closed, $this->private->live(), 'A new private manager was created.');
        self::assertSame('Works', $this->title('page:after'));
        self::assertSame(0, $this->rows('host_article'));
    }

    /**
     * FrankenPHP worker with the kernel kept alive and no services resetter: every request goes through http_kernel.
     * Another worker edits the row between requests; each request must read the current value, so the private
     * manager is dropped at the boundaries.
     */
    public function testWorkerBoundariesWithoutTheResetterDropThePrivateManager(): void
    {
        $http = self::getContainer()->get('http_kernel');
        self::assertInstanceOf(HttpKernel::class, $http);
        $this->store()->save(new SeoSurfaceData('page:home', 'en', 'start'));
        $baseline = 0;

        for ($i = 0; $i < 300; ++$i) {
            $this->connection->executeStatement(sprintf('UPDATE nowo_seo_surface SET %s = ? WHERE surface_key = ?', $this->titleColumn()), ['other-' . $i, 'page:home']);
            $request  = Request::create('https://example.test/_test/surface-title', 'GET', $i % 3 === 0 ? ['write' => 'mine-' . $i] : []);
            $response = $http->handle($request);
            self::assertSame($i % 3 === 0 ? 'mine-' . $i : 'other-' . $i, $response->getContent(), 'Request ' . $i);
            $http->terminate($request, $response);
            self::assertFalse($this->private->isCreated(), 'kernel.terminate dropped the private manager.');
            if ($i === 50) {
                // A dropped manager is a reference cycle (manager, unit of work, persisters): the cycle collector frees it.
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }
        gc_collect_cycles();
        self::assertLessThan(512 * 1024, memory_get_usage() - $baseline, 'No per-request growth from the dropped managers.');
        self::assertTrue($this->host->isOpen());
    }

    /** The 1.23.1 nested-command guard applies to the private manager too: an inner command keeps the outer unit's. */
    public function testNestedConsoleCommandKeepsTheOuterUnitManager(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $command   = new Command('test:outer');
        $input     = new ArrayInput([]);
        $output    = new NullOutput();
        $start     = new ConsoleCommandEvent($command, $input, $output);
        $terminate = new ConsoleTerminateEvent($command, $input, $output, 0);

        $dispatcher->dispatch($start, 'console.command');
        $this->store()->save(new SeoSurfaceData('page:home', 'en', 'outer'));
        $outer = $this->private->live();
        $dispatcher->dispatch($start, 'console.command');
        $dispatcher->dispatch($terminate, 'console.terminate');
        self::assertSame($outer, $this->private->live(), 'The inner command did not drop the outer unit manager.');
        $dispatcher->dispatch($terminate, 'console.terminate');
        self::assertFalse($this->private->isCreated(), 'The outermost terminate dropped it.');
    }

    /** Audits MQ-L-L3 / MO-Y-D-L1: the source filters are mirrored, with no stale parameter. */
    public function testSourceSqlFiltersAreMirroredWithoutStaleParameters(): void
    {
        $sourceFilters = $this->host->getFilters();
        $sourceFilters->enable('tenant')->setParameter('tenant', 'acme');
        $this->store()->findData('page:home', 'en');
        $filters = $this->private->live()->getFilters();
        self::assertTrue($filters->isEnabled('tenant'));
        self::assertSame("'acme'", $filters->getFilter('tenant')->getParameter('tenant'));

        // The source filter is re-created without the parameter: the old tenant must not survive.
        $sourceFilters->disable('tenant');
        $sourceFilters->enable('tenant');
        $this->store()->findData('page:home', 'en');
        $filters = $this->private->live()->getFilters();
        self::assertTrue($filters->isEnabled('tenant'));
        try {
            $filters->getFilter('tenant')->getParameter('tenant');
            self::fail('The stale tenant parameter must not survive.');
        } catch (InvalidArgumentException) {
        }

        $sourceFilters->getFilter('tenant')->setParameter('tenant', 'globex');
        self::assertSame("'globex'", $this->private->live()->getFilters()->getFilter('tenant')->getParameter('tenant'));
        $sourceFilters->getFilter('tenant')->setParameterList('regions', ['eu', 'us']);
        self::assertSame("'eu','us'", $this->private->live()->getFilters()->getFilter('tenant')->getParameterList('regions'));

        $sourceFilters->disable('tenant');
        self::assertFalse($this->private->live()->getFilters()->isEnabled('tenant'), 'A filter the source disabled is disabled.');
    }

    private function store(): SeoSurfaceStoreInterface
    {
        return $this->service('test.pem.store', SeoSurfaceStoreInterface::class);
    }

    private function manager(): SeoSurfaceManagerInterface
    {
        return $this->service('test.pem.manager', SeoSurfaceManagerInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }

    private function rows(string $table): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $table);
    }

    private function titleColumn(): string
    {
        return $this->host->getClassMetadata(SeoSurface::class)->getColumnName('metaTitle');
    }

    private function title(string $key): ?string
    {
        $title = $this->connection->fetchOne(sprintf('SELECT %s FROM nowo_seo_surface WHERE surface_key = ?', $this->titleColumn()), [$key]);

        return $title === false ? null : (string) $title;
    }
}
