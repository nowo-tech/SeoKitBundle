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
            'locales'  => ['en', 'es'],
            'sitemap'  => [
                'enabled'                  => true,
                'include_static_pages'     => true,
                'include_configured_slugs' => true,
            ],
            'pages' => [
                'app_home' => [
                    'path'               => '/',
                    'in_sitemap'         => true,
                    'sitemap_priority'   => 1.0,
                    'sitemap_changefreq' => 'daily',
                    'locales'            => ['es' => ['path' => '/es']],
                ],
            ],
            'slug_routes' => [
                'app_blog_show' => [
                    'path_pattern'       => '/blog/{slug}',
                    'sitemap_priority'   => 0.5,
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
        $request   = Request::create('https://example.com/');

        $entries = $generator->entries($request);

        $this->assertNotEmpty($entries);
        $locs = array_column($entries, 'loc');
        $this->assertContains('https://example.com/', $locs);
        $this->assertContains('https://example.com/es', $locs);
        $this->assertContains('https://example.com/blog/post-one', $locs);
    }

    public function testEntriesReturnsEmptyWhenSitemapDisabled(): void
    {
        $config    = ['sitemap' => ['enabled' => false]];
        $generator = new SitemapGenerator($config, new SeoPathBuilder($config));

        $this->assertSame([], $generator->entries(Request::create('/')));
    }

    public function testToXmlEscapesSpecialCharacters(): void
    {
        $config    = [];
        $generator = new SitemapGenerator($config, new SeoPathBuilder($config));
        $xml       = $generator->toXml([
            ['loc' => 'https://example.com/?a=1&b=2', 'changefreq' => 'weekly', 'priority' => '0.8'],
        ]);

        $this->assertStringContainsString('<?xml version="1.0"', $xml);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertStringContainsString('https://example.com/?a=1&amp;b=2', $xml);
    }

    public function testEntriesSkipsDisabledPagesInvalidLocalesAndMissingPaths(): void
    {
        $config = [
            'base_url' => 'https://example.com',
            'locales'  => ['en', 42, 'es'],
            'sitemap'  => [
                'enabled'                  => true,
                'include_static_pages'     => true,
                'include_configured_slugs' => true,
            ],
            'pages' => [
                'app_hidden'       => ['in_sitemap' => false, 'path' => '/hidden'],
                'app_missing_path' => ['in_sitemap' => true],
            ],
            'slug_routes' => [
                'app_blog_show' => ['path_pattern' => '/blog/{slug}'],
            ],
            'slugs' => [
                'app_blog_show' => [
                    'visible' => ['in_sitemap' => true],
                    'hidden'  => ['in_sitemap' => false],
                ],
                'not_array' => 'invalid',
            ],
        ];
        $generator = new SitemapGenerator($config, new SeoPathBuilder($config));

        $entries = $generator->entries(Request::create('https://example.com/'));
        $locs    = array_column($entries, 'loc');

        $this->assertContains('https://example.com/blog/visible', $locs);
        $this->assertNotContains('https://example.com/hidden', $locs);
        $this->assertNotContains('https://example.com/blog/hidden', $locs);
    }

    public function testEntriesSkipsSlugWhenPathCannotBeResolved(): void
    {
        $config = [
            'base_url' => 'https://example.com',
            'locales'  => ['en'],
            'sitemap'  => [
                'enabled'                  => true,
                'include_static_pages'     => false,
                'include_configured_slugs' => true,
            ],
            'slug_routes' => ['app_blog_show' => []],
            'slugs'       => [
                'app_blog_show' => [
                    'unresolved' => ['in_sitemap' => true],
                ],
            ],
        ];
        $generator = new SitemapGenerator($config, new SeoPathBuilder($config));

        $this->assertSame([], $generator->entries(Request::create('https://example.com/')));
    }
}
