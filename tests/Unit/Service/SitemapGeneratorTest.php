<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use Nowo\SeoKitBundle\Service\SitemapGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SitemapGeneratorTest extends TestCase
{
    public function testEntriesFromStaticPagesAndSlugs(): void
    {
        $config = [
            'base_url' => 'https://example.com',
            'locales' => ['en', 'es'],
            'sitemap' => [
                'enabled' => true,
                'include_static_pages' => true,
                'include_configured_slugs' => true,
            ],
            'pages' => [
                'app_home' => [
                    'path' => '/',
                    'in_sitemap' => true,
                    'sitemap_priority' => 1.0,
                    'sitemap_changefreq' => 'daily',
                    'locales' => ['es' => ['path' => '/es']],
                ],
            ],
            'slug_routes' => [
                'app_blog_show' => [
                    'path_pattern' => '/blog/{slug}',
                    'sitemap_priority' => 0.5,
                    'sitemap_changefreq' => 'weekly',
                ],
            ],
            'slugs' => [
                'app_blog_show' => [
                    'post-one' => ['in_sitemap' => true],
                ],
            ],
        ];
        $generator = new SitemapGenerator($config, new SeoPathBuilder($config));
        $request = Request::create('https://example.com/');

        $entries = $generator->entries($request);

        $this->assertNotEmpty($entries);
        $locs = array_column($entries, 'loc');
        $this->assertContains('https://example.com/', $locs);
        $this->assertContains('https://example.com/es', $locs);
        $this->assertContains('https://example.com/blog/post-one', $locs);
    }

    public function testEntriesReturnsEmptyWhenSitemapDisabled(): void
    {
        $config = ['sitemap' => ['enabled' => false]];
        $generator = new SitemapGenerator($config, new SeoPathBuilder($config));

        $this->assertSame([], $generator->entries(Request::create('/')));
    }

    public function testToXmlEscapesSpecialCharacters(): void
    {
        $config = [];
        $generator = new SitemapGenerator($config, new SeoPathBuilder($config));
        $xml = $generator->toXml([
            ['loc' => 'https://example.com/?a=1&b=2', 'changefreq' => 'weekly', 'priority' => '0.8'],
        ]);

        $this->assertStringContainsString('<?xml version="1.0"', $xml);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertStringContainsString('https://example.com/?a=1&amp;b=2', $xml);
    }
}
