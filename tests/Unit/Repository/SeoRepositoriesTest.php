<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Repository;

use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SeoRepositoriesTest extends TestCase
{
    public function testSiteSettingsGetOrCreateReturnsExisting(): void
    {
        $existing = new SeoSiteSettings();
        $em       = $this->entityManager();
        $repo     = $this->siteSettingsRepository($em, $existing);

        self::assertSame($existing, $repo->getOrCreate());
        $em->expects(self::never())->method('persist');
    }

    public function testSiteSettingsGetOrCreateRefreshesAlreadyManagedRow(): void
    {
        $existing = new SeoSiteSettings();
        $em       = $this->entityManager();
        $em->expects(self::once())->method('refresh')->with($existing);

        $this->siteSettingsRepository($em, $existing)->getOrCreate();
    }

    public function testSiteSettingsGetOrCreateReturnsRowCreatedConcurrentlyByAnotherWorker(): void
    {
        $winner = new SeoSiteSettings();
        $em     = $this->entityManager();
        $em->method('isOpen')->willReturn(false);
        $em->method('flush')->willThrowException($this->uniqueViolation());

        $repo = $this->siteSettingsRepository($em, null, found2: $winner, configureRegistry: static function (MockObject $registry): void {
            $registry->method('getManagerNames')->willReturn(['default' => 'doctrine.orm.default_entity_manager']);
            $registry->expects(self::once())->method('resetManager')->with('default');
        });

        self::assertSame($winner, $repo->getOrCreate());
    }

    public function testSiteSettingsGetOrCreateRethrowsUniqueViolationWhenRowStillMissing(): void
    {
        $em = $this->entityManager();
        $em->method('isOpen')->willReturn(true);
        $em->method('flush')->willThrowException($this->uniqueViolation());

        $repo = $this->siteSettingsRepository($em, null, configureRegistry: static function (MockObject $registry): void {
            $registry->expects(self::never())->method('resetManager');
        });

        $this->expectException(UniqueConstraintViolationException::class);
        $repo->getOrCreate();
    }

    public function testSiteSettingsSaveResetsClosedManagerAndRethrows(): void
    {
        $em = $this->entityManager();
        $em->method('isOpen')->willReturn(false);
        $em->method('flush')->willThrowException(new RuntimeException('Deadlock'));

        $other = $this->entityManager();
        $repo  = $this->siteSettingsRepository($em, null, configureRegistry: static function (MockObject $registry) use ($em, $other): void {
            $registry->method('getManagerNames')->willReturn(['other' => 'doctrine.orm.other_entity_manager', 'default' => 'doctrine.orm.default_entity_manager']);
            $registry->method('getManager')->willReturnCallback(static fn (?string $name): EntityManagerInterface => $name === 'default' ? $em : $other);
            $registry->expects(self::once())->method('resetManager')->with('default');
        });

        $this->expectException(RuntimeException::class);
        $repo->save(new SeoSiteSettings());
    }

    public function testSiteSettingsGetOrCreateSeedsFromEnvironment(): void
    {
        $em = $this->entityManager();
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(SeoSiteSettings::class));
        $em->expects(self::once())->method('flush');

        $repo     = $this->siteSettingsRepository($em, null, 'noindex, follow', 'ops@nowo.tech', 'Nowo');
        $settings = $repo->getOrCreate();

        self::assertFalse($settings->isIndexable());
        self::assertSame('noindex, follow', $settings->getDefaultRobots());
        self::assertSame('ops@nowo.tech', $settings->getContactEmail());
        self::assertSame('Nowo', $settings->getSiteName());
    }

    public function testSiteSettingsSaveTouchesAndFlushes(): void
    {
        $settings = new SeoSiteSettings();
        $em       = $this->entityManager();
        $em->expects(self::once())->method('persist')->with($settings);
        $em->expects(self::once())->method('flush');

        $repo = $this->siteSettingsRepository($em, $settings);
        $repo->save($settings);

        self::assertNotNull($settings->getUpdatedAt());
    }

    public function testSurfaceFindGetOrCreateAndSave(): void
    {
        $existing = new SeoSurface();
        $existing->setSurfaceKey('page:home')->setLocale('en');

        $em = $this->entityManager(SeoSurface::class);
        $em->expects(self::never())->method('persist');

        $repo = $this->surfaceRepository($em, $existing);
        self::assertSame($existing, $repo->findOneByKeyAndLocale('page:home', ' EN '));
        self::assertSame($existing, $repo->getOrCreate('page:home', 'en'));

        $emCreate = $this->entityManager(SeoSurface::class);
        $emCreate->expects(self::once())->method('persist')->with(self::callback(
            static fn (SeoSurface $s): bool => $s->getSurfaceKey() === 'page:about' && $s->getLocale() === 'es',
        ));
        $emCreate->expects(self::once())->method('flush');

        $created = $this->surfaceRepository($emCreate, null)->getOrCreate('page:about', 'ES');
        self::assertSame('page:about', $created->getSurfaceKey());
        self::assertSame('es', $created->getLocale());

        $toSave = new SeoSurface();
        $emSave = $this->entityManager(SeoSurface::class);
        $emSave->expects(self::once())->method('persist')->with($toSave);
        $emSave->expects(self::once())->method('flush');
        $this->surfaceRepository($emSave, null)->save($toSave);
    }

    public function testSurfaceGetOrCreateReturnsRowCreatedConcurrently(): void
    {
        $winner = (new SeoSurface())->setSurfaceKey('page:home')->setLocale('en');
        $em     = $this->entityManager(SeoSurface::class);
        $em->method('isOpen')->willReturn(true);
        $em->method('flush')->willThrowException($this->uniqueViolation());

        self::assertSame($winner, $this->surfaceRepository($em, null, $winner)->getOrCreate('page:home', 'en'));
    }

    public function testSurfaceGetOrCreateRethrowsUniqueViolationWhenRowStillMissing(): void
    {
        $em = $this->entityManager(SeoSurface::class);
        $em->method('isOpen')->willReturn(true);
        $em->method('flush')->willThrowException($this->uniqueViolation());

        $this->expectException(UniqueConstraintViolationException::class);
        $this->surfaceRepository($em, null)->getOrCreate('page:home', 'en');
    }

    public function testSurfaceRemoveFlushes(): void
    {
        $toRemove = new SeoSurface();
        $em       = $this->entityManager(SeoSurface::class);
        $em->expects(self::once())->method('remove')->with($toRemove);
        $em->expects(self::once())->method('flush');
        $this->surfaceRepository($em, null)->remove($toRemove);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $entityClass
     *
     * @return EntityManagerInterface&MockObject
     */
    private function entityManager(string $entityClass = SeoSiteSettings::class): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata($entityClass));

        return $em;
    }

    /**
     * @param (callable(ManagerRegistry&MockObject): void)|null $configureRegistry
     */
    private function siteSettingsRepository(
        EntityManagerInterface $em,
        ?SeoSiteSettings $found,
        string $robots = 'index, follow',
        string $email = '',
        string $siteName = '',
        false|SeoSiteSettings|null $found2 = false,
        ?callable $configureRegistry = null,
    ): SeoSiteSettingsRepository {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);
        if ($configureRegistry !== null) {
            $configureRegistry($registry);
        }
        $registry->method('getManager')->willReturn($em);

        return new class($registry, $found, $robots, $email, $siteName, $found2) extends SeoSiteSettingsRepository {
            private int $finds = 0;

            public function __construct(
                ManagerRegistry $registry,
                private readonly ?SeoSiteSettings $found,
                string $robots,
                string $email,
                string $siteName,
                private readonly false|SeoSiteSettings|null $found2,
            ) {
                parent::__construct($registry, $robots, $email, $siteName);
            }

            public function find(mixed $id, mixed $lockMode = null, mixed $lockVersion = null): ?object
            {
                return $this->finds++ === 0 || $this->found2 === false ? $this->found : $this->found2;
            }
        };
    }

    private function uniqueViolation(): UniqueConstraintViolationException
    {
        return new UniqueConstraintViolationException(new class('Duplicate entry') extends AbstractException {
        }, null);
    }

    private function surfaceRepository(EntityManagerInterface $em, ?SeoSurface $found, false|SeoSurface|null $found2 = false): SeoSurfaceRepository
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);
        $registry->method('getManager')->willReturn($em);

        return new class($registry, $found, $found2) extends SeoSurfaceRepository {
            private int $finds = 0;

            public function __construct(
                ManagerRegistry $registry,
                private readonly ?SeoSurface $found,
                private readonly false|SeoSurface|null $found2,
            ) {
                parent::__construct($registry);
            }

            public function findOneBy(array $criteria, ?array $orderBy = null): ?object
            {
                return $this->finds++ === 0 || $this->found2 === false ? $this->found : $this->found2;
            }
        };
    }
}
