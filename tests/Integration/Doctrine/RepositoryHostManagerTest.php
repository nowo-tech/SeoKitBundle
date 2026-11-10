<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Nowo\SeoKitBundle\Tests\Support\SqliteManagerRegistry;
use PHPUnit\Framework\TestCase;
use Throwable;

use function dirname;

/**
 * Rule 6 (W0): a failed repository flush resets only the manager of the SeoKit entities; a closed host manager is
 * never reset by the bundle. Real ORM: two in-memory SQLite entity managers behind a real ManagerRegistry.
 */
final class RepositoryHostManagerTest extends TestCase
{
    public function testFailedFlushResetsTheSeoManagerOnlyAndTheNextWriteWorks(): void
    {
        $registry = new SqliteManagerRegistry([
            'default' => ['App\\Entity' => dirname(__DIR__, 2)],
            'seo'     => ['Nowo\\SeoKitBundle\\Entity' => dirname(__DIR__, 3) . '/src/Entity'],
        ]);
        $host = $this->manager($registry, 'default');
        $host->close();
        $seo = $this->manager($registry, 'seo');
        (new SchemaTool($seo))->createSchema($seo->getMetadataFactory()->getAllMetadata());

        $repository = new SeoSurfaceRepository($registry);
        $repository->save($this->surface('home'));
        try {
            // Same key and locale: the unique constraint fails the flush and closes the seo manager.
            (new SeoSurfaceRepository($registry))->save($this->surface('home'));
            self::fail('A duplicate surface must fail.');
        } catch (Throwable) {
        }

        self::assertSame($host, $registry->getManager('default'), 'The bundle must never reset a host manager.');
        self::assertFalse($host->isOpen());
        self::assertTrue($this->manager($registry, 'seo')->isOpen());
        (new SeoSurfaceRepository($registry))->save($this->surface('about'));
        self::assertInstanceOf(SeoSurface::class, (new SeoSurfaceRepository($registry))->findOneByKeyAndLocale('about', 'en'));
    }

    private function surface(string $key): SeoSurface
    {
        $surface = new SeoSurface();
        $surface->setSurfaceKey($key);
        $surface->setLocale('en');

        return $surface;
    }

    private function manager(SqliteManagerRegistry $registry, string $name): EntityManagerInterface
    {
        $manager = $registry->getManager($name);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }
}
