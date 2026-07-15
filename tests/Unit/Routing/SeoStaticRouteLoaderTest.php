<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Routing;

use Nowo\SeoKitBundle\Routing\SeoStaticRouteLoader;
use PHPUnit\Framework\TestCase;

final class SeoStaticRouteLoaderTest extends TestCase
{
    public function testLoadsStaticPathsWithController(): void
    {
        $loader = new SeoStaticRouteLoader([
            'default_locale' => 'en',
            'pages' => [
                'app_about' => [
                    'controller' => 'App\\Controller\\AboutController::index',
                    'path' => '/about',
                    'locales' => [
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
        self::assertNotNull($routes->get('app_about'));
        self::assertSame('/about', $routes->get('app_about')->getPath());
        self::assertNotNull($routes->get('app_about.es'));
        self::assertSame('/es/sobre-nosotros', $routes->get('app_about.es')->getPath());
        self::assertNull($routes->get('ignored'));
    }
}
