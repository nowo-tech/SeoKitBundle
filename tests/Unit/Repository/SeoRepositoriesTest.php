<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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

    private function siteSettingsRepository(
        EntityManagerInterface $em,
        ?SeoSiteSettings $found,
        string $robots = 'index, follow',
        string $email = '',
        string $siteName = '',
    ): SeoSiteSettingsRepository {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);
        $registry->method('getManager')->willReturn($em);

        return new class($registry, $found, $robots, $email, $siteName) extends SeoSiteSettingsRepository {
            public function __construct(
                ManagerRegistry $registry,
                private readonly ?SeoSiteSettings $found,
                string $robots,
                string $email,
                string $siteName,
            ) {
                parent::__construct($registry, $robots, $email, $siteName);
            }

            public function find(mixed $id, mixed $lockMode = null, mixed $lockVersion = null): ?object
            {
                return $this->found;
            }
        };
    }

    private function surfaceRepository(EntityManagerInterface $em, ?SeoSurface $found): SeoSurfaceRepository
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);
        $registry->method('getManager')->willReturn($em);

        return new class($registry, $found) extends SeoSurfaceRepository {
            public function __construct(
                ManagerRegistry $registry,
                private readonly ?SeoSurface $found,
            ) {
                parent::__construct($registry);
            }

            public function findOneBy(array $criteria, ?array $orderBy = null): ?object
            {
                return $this->found;
            }
        };
    }
}
