<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Controller;

use Nowo\SeoKitBundle\Controller\SeoKitController;
use Nowo\SeoKitBundle\Service\RobotsTxtGenerator;
use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use Nowo\SeoKitBundle\Service\SitemapGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SeoKitControllerTest extends TestCase
{
    public function testSitemapAndRobots(): void
    {
        $config = [
            'locales' => ['en'],
            'default_locale' => 'en',
            'base_url' => 'https://example.com',
            'pages' => [
                'app_home' => ['path' => '/', 'in_sitemap' => true, 'sitemap_priority' => 1.0, 'sitemap_changefreq' => 'daily'],
            ],
            'slug_routes' => [],
            'slugs' => [],
            'sitemap' => ['enabled' => true, 'path' => '/sitemap.xml', 'include_static_pages' => true, 'include_configured_slugs' => true],
            'robots' => ['enabled' => true, 'user_agent' => '*', 'allow' => ['/'], 'disallow' => [], 'sitemap_link' => true],
        ];
        $paths = new SeoPathBuilder($config);
        $controller = new SeoKitController(new SitemapGenerator($config, $paths), new RobotsTxtGenerator($config, $paths), $config);
        $request = Request::create('https://example.com/sitemap.xml');

        $sitemap = $controller->sitemap($request);
        self::assertSame(200, $sitemap->getStatusCode());
        self::assertStringContainsString('<urlset', $sitemap->getContent() ?: '');
        self::assertStringContainsString('https://example.com/', $sitemap->getContent() ?: '');

        $robots = $controller->robots($request);
        self::assertSame(200, $robots->getStatusCode());
        self::assertStringContainsString('User-agent: *', $robots->getContent() ?: '');
        self::assertStringContainsString('Sitemap: https://example.com/sitemap.xml', $robots->getContent() ?: '');
    }

    public function testDisabledEndpointsReturn404(): void
    {
        $config = [
            'sitemap' => ['enabled' => false],
            'robots' => ['enabled' => false],
            'pages' => [],
            'slugs' => [],
            'locales' => ['en'],
        ];
        $paths = new SeoPathBuilder($config);
        $controller = new SeoKitController(new SitemapGenerator($config, $paths), new RobotsTxtGenerator($config, $paths), $config);
        $request = Request::create('/');

        self::assertSame(404, $controller->sitemap($request)->getStatusCode());
        self::assertSame(404, $controller->robots($request)->getStatusCode());
    }
}
