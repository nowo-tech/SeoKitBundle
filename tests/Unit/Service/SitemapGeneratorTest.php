<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use LogicException;
use Nowo\SeoKitBundle\Model\SitemapContext;
use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use Nowo\SeoKitBundle\Service\SeoPathBuilderInterface;
use Nowo\SeoKitBundle\Service\SiteIndexabilityProviderInterface;
use Nowo\SeoKitBundle\Service\SitemapEntryProviderInterface;
use Nowo\SeoKitBundle\Service\SitemapGenerator;
use Nowo\SeoKitBundle\Service\SitemapUrlProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

use function array_column;

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

    public function testIndexabilityProviderForcesEmptySitemap(): void
    {
        $config = [
            'indexable' => true,
            'locales'   => ['en'],
            'sitemap'   => ['enabled' => true, 'include_static_pages' => true],
            'pages'     => ['app_home' => ['path' => '/', 'in_sitemap' => true]],
        ];
        $provider = new class implements SiteIndexabilityProviderInterface {
            public function isIndexable(): bool
            {
                return false;
            }
        };
        $generator = new SitemapGenerator($config, new SeoPathBuilder($config), [], [$provider]);

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

    public function testEntriesSkipNoindexPagesAndSlugs(): void
    {
        $config = [
            'base_url' => 'https://example.com',
            'locales'  => ['en', 'es'],
            'sitemap'  => ['enabled' => true],
            'pages'    => [
                'app_home'    => ['path' => '/'],
                'app_private' => ['path' => '/private', 'robots' => 'noindex, follow'],
                'app_landing' => [
                    'path'    => '/landing',
                    'robots'  => 'index, follow',
                    'locales' => ['es' => ['path' => '/es/landing', 'robots' => 'NOINDEX']],
                ],
            ],
            'slug_routes' => ['app_blog_show' => ['path_pattern' => '/blog/{slug}']],
            'slugs'       => [
                'app_blog_show' => [
                    'public'  => [],
                    'draft'   => ['noindex' => true],
                    'archive' => ['robots' => 'noindex'],
                ],
            ],
        ];
        $generator = new SitemapGenerator($config, new SeoPathBuilder($config));

        $locs = array_column($generator->entries(Request::create('https://example.com/')), 'loc');

        $this->assertContains('https://example.com/', $locs);
        $this->assertContains('https://example.com/landing', $locs);
        $this->assertContains('https://example.com/blog/public', $locs);
        $this->assertNotContains('https://example.com/private', $locs);
        $this->assertNotContains('https://example.com/es/landing', $locs);
        $this->assertNotContains('https://example.com/blog/draft', $locs);
        $this->assertNotContains('https://example.com/blog/archive', $locs);
    }

    public function testToXmlEscapesQuotesInAttributes(): void
    {
        $generator = new SitemapGenerator([], new SeoPathBuilder([]));
        $xml       = $generator->toXml([[
            'loc'        => 'https://example.com/a?x="1"',
            'changefreq' => 'weekly',
            'priority'   => '0.5',
            'alternates' => [['hreflang' => 'es"x', 'href' => 'https://example.com/es" onload="x']],
        ]]);

        $this->assertStringContainsString('hreflang="es&quot;x"', $xml);
        $this->assertStringContainsString('href="https://example.com/es&quot; onload=&quot;x"', $xml);
        $this->assertStringNotContainsString('onload="x"', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    /**
     * @return array<string, mixed>
     */
    private function providerOnlyConfig(): array
    {
        return [
            'locales' => ['en', 'es'],
            'sitemap' => ['enabled' => true, 'include_static_pages' => false, 'include_configured_slugs' => false],
            'pages'   => [],
            'slugs'   => [],
        ];
    }

    /** Since 1.24.0: a provider of the framework-free port gets the context, from a request or without one. */
    public function testEntryProviderReceivesTheContext(): void
    {
        $provider = new class implements SitemapEntryProviderInterface {
            /** @var list<SitemapContext> */
            public array $contexts = [];

            public function getEntriesFor(SitemapContext $context): array
            {
                $this->contexts[] = $context;

                return [['loc' => $context->baseUrl() . '/' . ($context->locale ?? 'x') . '/page', 'priority' => '0.7']];
            }
        };
        $config    = $this->providerOnlyConfig();
        $generator = new SitemapGenerator($config, new SeoPathBuilder($config), [$provider]);

        $request = Request::create('https://shop.example.test/');
        $request->setLocale('es');
        $fromHttp = $generator->entries($request);
        $fromCli  = $generator->entriesFor(new SitemapContext('https://cli.example.test', 'en'));

        self::assertSame('https://shop.example.test/es/page', $fromHttp[0]['loc']);
        self::assertSame('0.7', $fromHttp[0]['priority']);
        self::assertSame('https://cli.example.test/en/page', $fromCli[0]['loc']);
        self::assertCount(2, $provider->contexts);
    }

    /** A provider implementing both interfaces is tagged twice: it is called once, through the new port. */
    public function testProviderImplementingBothPortsIsCalledOnceThroughTheNewOne(): void
    {
        $provider = new class implements SitemapEntryProviderInterface, SitemapUrlProviderInterface {
            public int $newCalls = 0;

            public function getEntriesFor(SitemapContext $context): array
            {
                ++$this->newCalls;

                return [['loc' => 'https://example.test/new']];
            }

            public function getEntries(Request $request): array
            {
                throw new LogicException('The deprecated method must not be called.');
            }
        };
        $config    = $this->providerOnlyConfig();
        $generator = new SitemapGenerator($config, new SeoPathBuilder($config), [$provider, $provider]);

        $entries = $generator->entriesFor(new SitemapContext('https://example.test'));

        self::assertSame(['https://example.test/new'], array_column($entries, 'loc'));
        self::assertSame(1, $provider->newCalls);
    }

    /** Deprecated providers keep working without HTTP: they get a synthetic request built from the context. */
    public function testDeprecatedProviderGetsASyntheticRequestWithoutHttp(): void
    {
        $provider = new class implements SitemapUrlProviderInterface {
            public function getEntries(Request $request): array
            {
                return [['loc' => $request->getSchemeAndHttpHost() . '/' . $request->getLocale() . '/legacy']];
            }
        };
        $config    = $this->providerOnlyConfig();
        $generator = new SitemapGenerator($config, new SeoPathBuilder($config), [$provider]);

        $withLocale    = $generator->entriesFor(new SitemapContext('https://legacy.example.test', 'es'));
        $withoutLocale = $generator->entriesFor(new SitemapContext('https://legacy.example.test'));

        self::assertSame('https://legacy.example.test/es/legacy', $withLocale[0]['loc']);
        self::assertSame('https://legacy.example.test/en/legacy', $withoutLocale[0]['loc']);
    }

    /** A host path builder that implements only the deprecated port keeps being used (request or synthetic one). */
    public function testDeprecatedOnlyPathBuilderIsStillUsed(): void
    {
        $config = [
            'locales' => ['en'],
            'sitemap' => ['enabled' => true, 'include_configured_slugs' => false],
            'pages'   => ['app_home' => ['path' => '/home']],
        ];
        $inner = new SeoPathBuilder($config);
        $paths = new class($inner) implements SeoPathBuilderInterface {
            public function __construct(private readonly SeoPathBuilder $inner)
            {
            }

            public function absoluteUrl(Request $request, string $path): string
            {
                return 'legacy:' . $request->getSchemeAndHttpHost() . $path;
            }

            public function pagePath(string $route, string $locale, ?string $fallbackPath = null): ?string
            {
                return $this->inner->pagePath($route, $locale, $fallbackPath);
            }

            public function resolveCanonicalSlug(string $route, string $slug): string
            {
                return $this->inner->resolveCanonicalSlug($route, $slug);
            }

            public function slugPath(string $route, string $locale, string $slug): ?string
            {
                return $this->inner->slugPath($route, $locale, $slug);
            }
        };
        $generator = new SitemapGenerator($config, $paths);

        self::assertSame('legacy:https://a.example.test/home', $generator->entries(Request::create('https://a.example.test/'))[0]['loc']);
        self::assertSame('legacy:https://b.example.test/home', $generator->entriesFor(new SitemapContext('https://b.example.test'))[0]['loc']);
    }
}
