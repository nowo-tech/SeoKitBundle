<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Routing;

use Nowo\SeoKitBundle\Routing\SeoStaticRouteLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SeoStaticRouteLoaderTest extends TestCase
{
    public function testLoadsStaticPathsWithController(): void
    {
        $loader = new SeoStaticRouteLoader([
            'default_locale' => 'en',
            'sitemap'        => ['enabled' => true, 'path' => '/sitemap.xml'],
            'robots'         => ['enabled' => true, 'path' => '/robots.txt'],
            'pages'          => [
                'app_about' => [
                    'controller' => 'App\\Controller\\AboutController::index',
                    'path'       => '/about',
                    'locales'    => [
                        'es' => ['path' => '/es/sobre-nosotros'],
                    ],
                ],
                'ignored' => [
                    'path' => '/x',
                ],
            ],
        ]);

        self::assertTrue($loader->supports(null, 'nowo_seo_kit'));
        self::assertFalse($loader->supports(null, 'yaml'));

        $routes = $loader->load(null, 'nowo_seo_kit');
        self::assertNotNull($routes->get('nowo_seo_kit_sitemap'));
        self::assertSame('/sitemap.xml', $routes->get('nowo_seo_kit_sitemap')->getPath());
        self::assertNotNull($routes->get('nowo_seo_kit_robots'));
        self::assertSame('/robots.txt', $routes->get('nowo_seo_kit_robots')->getPath());
        self::assertNotNull($routes->get('app_about'));
        self::assertSame('/about', $routes->get('app_about')->getPath());
        self::assertNotNull($routes->get('app_about.es'));
        self::assertSame('/es/sobre-nosotros', $routes->get('app_about.es')->getPath());
        self::assertNull($routes->get('ignored'));
    }

    public function testSkipsDisabledSitemapAndRobots(): void
    {
        $loader = new SeoStaticRouteLoader([
            'sitemap' => ['enabled' => false],
            'robots'  => ['enabled' => false],
            'pages'   => [],
        ]);

        $routes = $loader->load(null, 'nowo_seo_kit');

        self::assertNull($routes->get('nowo_seo_kit_sitemap'));
        self::assertNull($routes->get('nowo_seo_kit_robots'));
    }

    public function testLoadThrowsWhenCalledTwice(): void
    {
        $loader = new SeoStaticRouteLoader([
            'pages' => [
                'app_about' => [
                    'controller' => 'App\\Controller\\AboutController::index',
                    'path'       => '/about',
                ],
            ],
        ]);
        $loader->load(null, 'nowo_seo_kit');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SeoStaticRouteLoader already loaded.');
        $loader->load(null, 'nowo_seo_kit');
    }

    public function testLoadSkipsLocaleEntriesWithoutPath(): void
    {
        $loader = new SeoStaticRouteLoader([
            'default_locale' => 'en',
            'pages'          => [
                'app_about' => [
                    'controller' => 'App\\Controller\\AboutController::index',
                    'path'       => '/about',
                    'locales'    => [
                        'es' => ['title' => 'Sobre nosotros'],
                    ],
                ],
            ],
        ]);

        $routes = $loader->load(null, 'nowo_seo_kit');

        self::assertNotNull($routes->get('app_about'));
        self::assertNull($routes->get('app_about.es'));
    }
}
