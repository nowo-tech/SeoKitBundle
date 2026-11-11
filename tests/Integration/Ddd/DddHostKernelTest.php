<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Ddd;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Model\SeoSurfaceData;
use Nowo\SeoKitBundle\Model\SitemapContext;
use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use Nowo\SeoKitBundle\Service\SeoSurfaceStoreInterface;
use Nowo\SeoKitBundle\Service\SitemapGenerator;
use Nowo\SeoKitBundle\Tests\Integration\Ddd\Domain\HostArticle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * REQ-ARCH-001, DDD host (since 1.24.0): the host uses only the framework-free ports (SitemapEntryProviderInterface,
 * SeoUrlBuilderInterface, SeoSurfaceStoreInterface with SeoSurfaceData), maps its own domain in XML, and keeps the
 * SeoKit entities on a dedicated manager. Rule 6: the bundle's writes never flush the host's pending changes.
 */
final class DddHostKernelTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return DddHostKernel::class;
    }

    public function testSitemapOverHttpUsesTheContextOnlyProvider(): void
    {
        self::bootKernel();
        $this->createSchemas();
        $http = self::getContainer()->get('http_kernel');
        self::assertInstanceOf(HttpKernelInterface::class, $http);

        $response = $http->handle(Request::create('https://blog.example.test/sitemap.xml'));

        self::assertSame(200, $response->getStatusCode());
        $xml = (string) $response->getContent();
        self::assertStringContainsString('<loc>https://blog.example.test/</loc>', $xml);
        self::assertStringContainsString('<loc>https://blog.example.test/en/blog/hello-world</loc>', $xml);
        self::assertStringContainsString('<loc>https://blog.example.test/en/blog/second-post</loc>', $xml);
    }

    public function testSitemapWithoutHttpFromAnApplicationService(): void
    {
        self::bootKernel();
        $this->createSchemas();
        $generator = self::getContainer()->get('test.ddd.sitemap_generator');
        self::assertInstanceOf(SitemapGenerator::class, $generator);
        self::assertInstanceOf(SeoPathBuilder::class, self::getContainer()->get('test.ddd.url_builder'));

        $locs = array_column($generator->entriesFor(new SitemapContext('https://cli.example.test', 'es')), 'loc');

        self::assertSame([
            'https://cli.example.test/',
            'https://cli.example.test/es/blog/hello-world',
            'https://cli.example.test/es/blog/second-post',
        ], $locs);
    }

    public function testSurfaceStoreWritesOnTheBundleManagerAndLeavesHostPendingChangesUntouched(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $doctrine  = $container->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $doctrine);
        $host = $this->manager($doctrine, 'default');
        $seo  = $this->manager($doctrine, 'seo');
        $this->createSchemas();
        self::assertTrue($seo->getMetadataFactory()->isTransient(HostArticle::class));
        self::assertTrue($host->getMetadataFactory()->isTransient(SeoSurface::class));

        $store = $container->get('test.ddd.surface_store');
        self::assertInstanceOf(SeoSurfaceStoreInterface::class, $store);

        // The host has a pending, unflushed change when it calls the bundle.
        $article = new HostArticle('draft');
        $host->persist($article);

        self::assertTrue($store->save(new SeoSurfaceData('blog:draft', 'en', 'Draft title', metaRobots: 'noindex')));
        self::assertEquals(new SeoSurfaceData('blog:draft', 'en', 'Draft title', metaRobots: 'noindex'), $store->findData('blog:draft', 'en'));
        self::assertTrue($store->save(new SeoSurfaceData('blog:draft', 'en', 'Second title')));
        $second = $store->findData('blog:draft', 'en');
        self::assertNotNull($second);
        self::assertSame('Second title', $second->metaTitle);
        self::assertNull($second->metaRobots);
        self::assertTrue($store->clear('blog:draft', 'en'));
        self::assertNull($store->findData('blog:draft', 'en'));
        self::assertFalse($store->save(new SeoSurfaceData('blog:empty', 'en')));
        self::assertSame(0, (int) $seo->getConnection()->fetchOne('SELECT COUNT(*) FROM nowo_seo_surface'));

        self::assertTrue($host->getUnitOfWork()->isScheduledForInsert($article), 'The host change is still pending.');
        self::assertNull($article->id());
        self::assertSame(0, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM host_article'), 'Nothing of the host was flushed.');

        $host->flush();
        self::assertSame(1, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM host_article'));
    }

    private function createSchemas(): void
    {
        $doctrine = self::getContainer()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $doctrine);
        foreach (['default', 'seo'] as $name) {
            $manager = $this->manager($doctrine, $name);
            (new SchemaTool($manager))->createSchema($manager->getMetadataFactory()->getAllMetadata());
        }
    }

    private function manager(ManagerRegistry $doctrine, string $name): EntityManagerInterface
    {
        $manager = $doctrine->getManager($name);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }
}
