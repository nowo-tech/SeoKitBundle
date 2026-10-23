<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration;

use Nowo\SeoKitBundle\Doctrine\TablePrefixListener;
use Nowo\SeoKitBundle\Service\SitemapGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

use function sys_get_temp_dir;

/**
 * Boots a real kernel with the bundle and serves sitemap.xml / robots.txt through the HTTP kernel.
 */
final class SeoKitKernelTest extends TestCase
{
    private SeoKitTestKernel $kernel;

    protected function setUp(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo-seo-kit-integration');
        $this->kernel = new SeoKitTestKernel('test', false);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo-seo-kit-integration');
    }

    public function testContainerCompilesWithBundleDefaults(): void
    {
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        self::assertTrue($container->has(SitemapGenerator::class));
        self::assertFalse($container->has(TablePrefixListener::class));
    }

    public function testSitemapIsServedWithLocalisedUrls(): void
    {
        $response = $this->kernel->handle(Request::create('https://example.test/sitemap.xml'));

        self::assertSame(200, $response->getStatusCode());
        $xml = (string) $response->getContent();
        self::assertStringContainsString('<urlset', $xml);
        self::assertStringContainsString('https://example.test/es', $xml);
    }

    public function testRobotsTxtIsServed(): void
    {
        $response = $this->kernel->handle(Request::create('https://example.test/robots.txt'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('User-agent', (string) $response->getContent());
    }
}
