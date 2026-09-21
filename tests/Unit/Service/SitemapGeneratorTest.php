<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use Nowo\SeoKitBundle\Service\SitemapGenerator;
use Nowo\SeoKitBundle\Service\SitemapUrlProviderInterface;
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

    public function testEntriesEmptyWhenNotIndexable(): void
    {
        $config = [
            'indexable' => false,
            'locales'   => ['en'],
            'sitemap'   => ['enabled' => true, 'include_static_pages' => true],
            'pages'     => ['app_home' => ['path' => '/', 'in_sitemap' => true]],
        ];
        $generator = new SitemapGenerator($config, new SeoPathBuilder($config));

        $this->assertFalse($generator->isIndexable());
        $this->assertSame([], $generator->entries(Request::create('/')));
    }

    public function testUrlProviderEntriesAndXhtmlAlternates(): void
    {
        $config = [
            'locales' => ['en'],
            'sitemap' => [
                'enabled'                  => true,
                'include_static_pages'     => false,
                'include_configured_slugs' => false,
            ],
            'pages' => [],
            'slugs' => [],
        ];
        $provider = new class implements SitemapUrlProviderInterface {
            public function getEntries(Request $request): array
            {
                /** @var list<array{loc: string, changefreq?: string, priority?: string, lastmod?: string, alternates?: list<array{hreflang: string, href: string}>}> $entries */
                $entries = [
                    [
                        'loc'        => 'https://example.com/cms/page',
                        'changefreq' => 'daily',
                        'priority'   => '0.9',
                        'lastmod'    => '2026-09-21',
                        'alternates' => [
                            ['hreflang' => 'es', 'href' => 'https://example.com/es/cms/page'],
                        ],
                    ],
                    ['loc' => ''],
                ];

                return $entries;
            }
        };
        $generator = new SitemapGenerator($config, new SeoPathBuilder($config), [$provider]);
        $entries   = $generator->entries(Request::create('/'));

        $this->assertCount(1, $entries);
        $this->assertSame('https://example.com/cms/page', $entries[0]['loc']);
        $this->assertArrayHasKey('lastmod', $entries[0]);
        $this->assertSame('2026-09-21', $entries[0]['lastmod']);
        $this->assertArrayHasKey('alternates', $entries[0]);
        $this->assertCount(1, $entries[0]['alternates']);

        $xml = $generator->toXml($entries);
        $this->assertStringContainsString('xmlns:xhtml=', $xml);
        $this->assertStringContainsString('xhtml:link rel="alternate"', $xml);
        $this->assertStringContainsString('hreflang="es"', $xml);
    }
}
