<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Worker;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Throwable;

/**
 * FrankenPHP worker mode with the kernel kept alive and never reset (FRANKENPHP_RESET_KERNEL=false, no services
 * resetter): one booted kernel serves every request through the http_kernel service, so kernel.reset never runs.
 * The SeoKit entities live on their own manager ("seo", doctrine.entity_manager). A failed SeoKit flush closes it;
 * the bundle reopens it and later requests write again, while the host's closed manager is never touched.
 */
final class WorkerEntityManagerRecoveryTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return WorkerTestKernel::class;
    }

    public function testBundleManagerRecoversAcrossRequestsAndHostManagerIsUntouched(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $doctrine  = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $doctrine);
        $host = $this->manager($doctrine, 'default');
        $seo  = $this->manager($doctrine, 'seo');
        self::assertSame($seo, $doctrine->getManagerForClass(SeoSurface::class), 'doctrine.entity_manager maps the entities on "seo".');
        (new SchemaTool($seo))->createSchema($seo->getMetadataFactory()->getAllMetadata());
        $repository = $container->get('test.worker.seo_surface_repository');
        self::assertInstanceOf(SeoSurfaceRepository::class, $repository);
        $http = $container->get('http_kernel');
        self::assertInstanceOf(HttpKernelInterface::class, $http);

        $host->close();
        $repository->getOrCreate('home', 'en');
        $this->serve($http);

        try {
            $duplicate = new SeoSurface();
            $duplicate->setSurfaceKey('home');
            $duplicate->setLocale('en');
            $repository->save($duplicate);
            self::fail('A duplicate surface must fail.');
        } catch (Throwable) {
        }
        self::assertTrue($seo->isOpen(), 'The bundle reopens its own manager after the failed flush.');
        self::assertFalse($host->isOpen(), 'The bundle must never reset the host manager.');

        for ($i = 0; $i < 20; ++$i) {
            $this->serve($http);
            $repository->getOrCreate('page-' . $i, 'en');
            self::assertNotNull($repository->findOneByKeyAndLocale('page-' . $i, 'en'));
        }
        self::assertFalse($host->isOpen(), 'Still closed: only the host decides when to reset it.');
    }

    private function manager(ManagerRegistry $doctrine, string $name): EntityManagerInterface
    {
        $manager = $doctrine->getManager($name);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    private function serve(HttpKernelInterface $http): void
    {
        $request  = Request::create('/sitemap.xml');
        $response = $http->handle($request);
        self::assertSame(200, $response->getStatusCode());
        if ($http instanceof TerminableInterface) {
            $http->terminate($request, $response);
        }
    }
}
