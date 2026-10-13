<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Model\SeoMetadata;
use Nowo\SeoKitBundle\Service\SeoDefaultsProviderInterface;
use Nowo\SeoKitBundle\Service\SeoMetadataResolver;
use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use Nowo\SeoKitBundle\Service\SeoRuntime;
use Nowo\SeoKitBundle\Service\SeoTemplateRenderer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class SeoMetadataResolverTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     * @param iterable<SeoDefaultsProviderInterface> $defaultsProviders
     */
    private function createResolver(
        array $config,
        ?Request $request = null,
        ?SeoRuntime $runtime = null,
        iterable $defaultsProviders = [],
    ): SeoMetadataResolver {
        $requestStack = new RequestStack();
        if ($request instanceof Request) {
            $requestStack->push($request);
        }

        $runtime ??= new SeoRuntime();
        $paths        = new SeoPathBuilder($config);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $params = []): string => '/generated/' . $route . '?' . http_build_query($params),
        );

        return new SeoMetadataResolver(
            $config,
            $requestStack,
            $runtime,
            new SeoTemplateRenderer(),
            $paths,
            $urlGenerator,
            $defaultsProviders,
        );
    }

    public function testResolveReturnsDisabledMetadataWhenNoRequest(): void
    {
        $metadata = $this->createResolver(['enabled' => true])->resolve();

        $this->assertSame('', $metadata->title);
        $this->assertSame('noindex,nofollow', $metadata->robots);
        $this->assertSame('disabled', $metadata->source);
    }

    public function testResolveMergesDefaultsPagesAndSlugLayers(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en', 'es'],
            'defaults'       => [
                'site_name'       => 'Demo',
                'title_separator' => ' | ',
                'title_template'  => '{title}{separator}{site_name}',
                'description'     => 'Default description',
            ],
            'slug_routes' => [
                'app_blog_show' => [
                    'slug_parameter'       => 'slug',
                    'title_template'       => 'Blog: {slug}',
                    'description_template' => 'Post {slug}',
                    'path_pattern'         => '/blog/{slug}',
                ],
            ],
            'pages' => [
                'app_blog_show' => [
                    'title'  => 'Blog',
                    'robots' => 'index,follow',
                ],
            ],
            'slugs' => [
                'app_blog_show' => [
                    'hello' => [
                        'title'       => 'Hello post',
                        'description' => 'Hello description',
                    ],
                ],
            ],
        ];

        $request = Request::create('/blog/hello', 'GET');
        $request->attributes->set('_route', 'app_blog_show');
        $request->attributes->set('_locale', 'en');
        $request->attributes->set('slug', 'hello');

        $metadata = $this->createResolver($config, $request)->resolve();

        $this->assertSame('Hello post | Demo', $metadata->title);
        $this->assertSame('Hello description', $metadata->description);
        $this->assertSame('slugs', $metadata->source);
        $this->assertStringContainsString('/blog/hello', (string) $metadata->canonical);
    }

    public function testResolveAppliesNoindexFromSlugConfig(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => ['site_name' => ''],
            'slug_routes'    => [
                'app_blog_show' => ['slug_parameter' => 'slug', 'path_pattern' => '/blog/{slug}'],
            ],
            'slugs' => [
                'app_blog_show' => [
                    'secret' => ['noindex' => true, 'title' => 'Secret'],
                ],
            ],
        ];

        $request = Request::create('/blog/secret');
        $request->attributes->set('_route', 'app_blog_show');
        $request->attributes->set('slug', 'secret');

        $metadata = $this->createResolver($config, $request)->resolve();

        $this->assertSame('noindex,nofollow', $metadata->robots);
    }

    public function testResolveAppliesRuntimeOverrides(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => [
                'site_name'      => 'Demo',
                'title_template' => '{title}{separator}{site_name}',
            ],
            'pages' => [
                'app_home' => ['title' => 'Home', 'path' => '/'],
            ],
        ];

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        $runtime = new SeoRuntime();
        $runtime->set(['title' => 'Runtime title', 'description' => 'Runtime desc']);
        $runtime->setVariables(['title' => 'Runtime title']);

        $metadata = $this->createResolver($config, $request, $runtime)->resolve();

        $this->assertSame('Runtime title | Demo', $metadata->title);
        $this->assertSame('Runtime desc', $metadata->description);
        $this->assertSame('runtime', $metadata->source);
    }

    public function testResolveBuildsHreflangAlternates(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en', 'es'],
            'defaults'       => [
                'site_name'          => '',
                'hreflang_enabled'   => true,
                'x_default_hreflang' => true,
            ],
            'pages' => [
                'app_home' => [
                    'title'   => 'Home',
                    'path'    => '/',
                    'locales' => ['es' => ['path' => '/es']],
                ],
            ],
        ];

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $request->attributes->set('_locale', 'en');

        $metadata  = $this->createResolver($config, $request)->resolve();
        $hreflangs = array_column($metadata->alternates, 'hreflang');

        $this->assertContains('en', $hreflangs);
        $this->assertContains('es', $hreflangs);
        $this->assertContains('x-default', $hreflangs);
    }

    public function testResolveStripsTrailingSeparatorWhenSiteNameEmpty(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => [
                'site_name'       => '',
                'title_separator' => ' | ',
                'title_template'  => '{title}{separator}{site_name}',
            ],
            'pages' => ['app_home' => ['title' => 'Only title', 'path' => '/']],
        ];

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $request->attributes->set('title', 'Only title');

        $metadata = $this->createResolver($config, $request)->resolve();

        $this->assertSame('Only title', $metadata->title);
    }

    public function testResolveReturnsDisabledMetadataWhenBundleDisabled(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        $metadata = $this->createResolver(['enabled' => false], $request)->resolve();

        $this->assertSame('disabled', $metadata->source);
    }

    public function testResolveMergesLocaleSpecificSlugRouteAndPageLayers(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'es',
            'locales'        => ['es'],
            'defaults'       => ['site_name' => 'Demo', 'title_template' => '{title}'],
            'slug_routes'    => [
                'app_blog_show' => [
                    'slug_parameter' => 'slug',
                    'title'          => 'Blog route',
                    'path_pattern'   => '/blog/{slug}',
                    'locales'        => [
                        'es' => ['title' => 'Blog ES route'],
                    ],
                ],
            ],
            'pages' => [
                'app_blog_show' => [
                    'title'   => 'Blog page',
                    'locales' => [
                        'es' => ['title' => 'Blog ES page'],
                    ],
                ],
            ],
            'slugs' => [
                'app_blog_show' => [
                    'hello' => [
                        'title'   => 'Hello slug',
                        'locales' => [
                            'es' => ['title' => 'Hola slug'],
                        ],
                    ],
                ],
            ],
        ];

        $request = Request::create('/blog/hello');
        $request->attributes->set('_route', 'app_blog_show');
        $request->attributes->set('_locale', 'es');
        $request->attributes->set('slug', 'hello');

        $metadata = $this->createResolver($config, $request)->resolve();

        $this->assertSame('Hola slug', $metadata->title);
        $this->assertSame('slugs', $metadata->source);
    }

    public function testResolveUsesTitleAndDescriptionTemplatesWhenExplicitValuesMissing(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => [
                'site_name'       => 'Demo',
                'title_template'  => '{title}{separator}{site_name}',
                'title_separator' => ' | ',
            ],
            'pages' => [
                'app_home' => [
                    'title_template'       => 'Welcome {locale}',
                    'description_template' => 'Desc {locale}',
                    'path'                 => '/',
                ],
            ],
        ];

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $request->attributes->set('_locale', 'en');

        $metadata = $this->createResolver($config, $request)->resolve();

        $this->assertSame('Welcome en | Demo', $metadata->title);
        $this->assertSame('Desc en', $metadata->description);
    }

    public function testResolveAppliesRuntimeNoindexOverride(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => ['site_name' => '', 'title_template' => '{title}'],
            'pages'          => ['app_home' => ['title' => 'Home', 'path' => '/']],
        ];

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        $runtime = new SeoRuntime();
        $runtime->set(['title' => 'Hidden', 'noindex' => true]);

        $metadata = $this->createResolver($config, $request, $runtime)->resolve();

        $this->assertSame('noindex,nofollow', $metadata->robots);
        $this->assertSame('runtime', $metadata->source);
    }

    public function testResolveUsesExplicitCanonicalAndNestedSocialMetadata(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'base_url'       => 'https://example.com',
            'defaults'       => [
                'site_name'         => 'Demo',
                'title_template'    => '{title}',
                'canonical_enabled' => true,
                'hreflang_enabled'  => false,
                'json_ld'           => [
                    'enabled'      => true,
                    'organization' => ['name' => 'Demo Org', 'url' => 'https://example.com'],
                    'extra'        => [['@type' => 'BreadcrumbList', 'itemListElement' => []], 'skip'],
                ],
            ],
            'pages' => [
                'app_home' => [
                    'title'      => 'Home',
                    'canonical'  => '/canonical-home',
                    'open_graph' => ['type' => 'article', 'image' => '/og.png'],
                    'twitter'    => ['image' => '/twitter.png', 'site' => '@demo'],
                    'path'       => '/',
                ],
            ],
        ];

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        $metadata = $this->createResolver($config, $request)->resolve();

        $this->assertSame('https://example.com/canonical-home', $metadata->canonical);
        $this->assertSame('article', $metadata->openGraph['type']);
        $this->assertSame('https://example.com/twitter.png', $metadata->twitter['image']);
        $this->assertSame('Organization', $metadata->jsonLd['graph'][0]['@type']);
        $this->assertSame('BreadcrumbList', $metadata->jsonLd['graph'][2]['@type']);
    }

    public function testResolveOmitsCanonicalWhenDisabled(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'base_url'       => 'https://example.com',
            'defaults'       => [
                'site_name'         => 'Demo',
                'title_template'    => '{title}',
                'canonical_enabled' => false,
                'hreflang_enabled'  => false,
            ],
            'pages' => [
                'app_home' => [
                    'title' => 'Home',
                    'path'  => '/',
                ],
            ],
        ];

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        $metadata = $this->createResolver($config, $request)->resolve();

        $this->assertNull($metadata->canonical);
    }

    public function testResolveFallsBackToRequestPathForCanonicalWhenNoConfiguredPath(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => [
                'site_name'         => '',
                'title_template'    => '{title}',
                'canonical_enabled' => true,
                'hreflang_enabled'  => false,
            ],
            'pages' => [
                'app_dynamic' => ['title' => 'Dynamic'],
            ],
        ];

        $request = Request::create('/dynamic/path');
        $request->attributes->set('_route', 'app_dynamic');

        $metadata = $this->createResolver($config, $request)->resolve();

        $this->assertSame('http://localhost/dynamic/path', $metadata->canonical);
    }

    public function testResolveUsesSiteNameWhenNoTitleConfigured(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => [
                'site_name'       => 'Only Site',
                'title_template'  => '{title}{separator}{site_name}',
                'title_separator' => ' | ',
            ],
            'pages' => [
                'app_home' => ['path' => '/'],
            ],
        ];

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        $metadata = $this->createResolver($config, $request)->resolve();

        $this->assertSame('Only Site | Only Site', $metadata->title);
    }

    public function testResolveSkipsNonStringLocalesInAlternates(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en', 99],
            'defaults'       => [
                'site_name'          => '',
                'title_template'     => '{title}',
                'hreflang_enabled'   => true,
                'x_default_hreflang' => false,
            ],
            'pages' => [
                'app_home' => ['title' => 'Home', 'path' => '/'],
            ],
        ];

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $request->attributes->set('_locale', 'en');

        $metadata  = $this->createResolver($config, $request)->resolve();
        $hreflangs = array_column($metadata->alternates, 'hreflang');

        $this->assertSame(['en'], $hreflangs);
    }

    public function testResolveIgnoresControllerWithoutSeoAttribute(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => ['site_name' => '', 'title_template' => '{title}'],
            'pages'          => ['app_plain' => ['title' => 'Plain', 'path' => '/plain']],
        ];

        $request = Request::create('/plain');
        $request->attributes->set('_route', 'app_plain');
        $request->attributes->set('_controller', SeoPlainFixtureController::class . '::index');

        $metadata = $this->createResolver($config, $request)->resolve();

        $this->assertSame('Plain', $metadata->title);
        $this->assertSame('pages', $metadata->source);
    }

    public function testResolveBuildsAlternatesViaUrlGeneratorWhenPathsMissing(): void
    {
        $metadata = $this->resolveWithRealRouter('/en/products/7', 'app_products', ['_locale' => 'en', 'id' => 7]);

        self::assertSame([
            'https://example.com/en/products/7',
            'https://example.com/es/products/7',
        ], array_column($metadata->alternates, 'url'));
        self::assertSame(['en', 'es'], array_column($metadata->alternates, 'hreflang'));
    }

    public function testResolveUsesLocalizedRouteVariantsForAlternates(): void
    {
        $metadata = $this->resolveWithRealRouter('/contact', 'app_contact', ['_locale' => 'en']);

        self::assertSame([
            'https://example.com/contact',
            'https://example.com/contacto',
        ], array_column($metadata->alternates, 'url'));
    }

    public function testResolveSkipsAlternatesForRoutesWithoutLocalePathVariable(): void
    {
        $metadata = $this->resolveWithRealRouter('/about', 'app_about', [], 'en');

        // Only the current page: no "/about?_locale=es" pseudo-alternate.
        self::assertSame(['https://example.com/about'], array_column($metadata->alternates, 'url'));
        foreach ($metadata->alternates as $alternate) {
            self::assertStringNotContainsString('_locale=', $alternate['url']);
        }
    }

    /**
     * @param array<string, mixed> $routeParams
     */
    private function resolveWithRealRouter(string $path, string $route, array $routeParams, string $locale = 'en'): SeoMetadata
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en', 'es'],
            'base_url'       => 'https://example.com',
            'defaults'       => [
                'site_name'          => '',
                'title_template'     => '{title}',
                'hreflang_enabled'   => true,
                'x_default_hreflang' => false,
            ],
            'pages' => [
                $route => ['title' => 'Dynamic'],
            ],
        ];

        $routes = new RouteCollection();
        $routes->add('app_products', new Route('/{_locale}/products/{id}'));
        $routes->add('app_about', new Route('/about'));
        $routes->add('app_contact.en', new Route('/contact', ['_locale' => 'en', '_canonical_route' => 'app_contact']));
        $routes->add('app_contact.es', new Route('/contacto', ['_locale' => 'es', '_canonical_route' => 'app_contact']));

        $request = Request::create($path);
        $request->attributes->set('_route', $route);
        $request->attributes->set('_locale', $locale);
        $request->attributes->set('_route_params', $routeParams);

        $stack = new RequestStack();
        $stack->push($request);

        $resolver = new SeoMetadataResolver(
            $config,
            $stack,
            new SeoRuntime(),
            new SeoTemplateRenderer(),
            new SeoPathBuilder($config),
            new UrlGenerator($routes, new RequestContext()),
        );

        return $resolver->resolve();
    }

    public function testIndexableFalseOverridesRobotsFromLayers(): void
    {
        $config = [
            'enabled'        => true,
            'indexable'      => false,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => ['site_name' => 'Site', 'title_template' => '{title}', 'robots' => 'index, follow', 'hreflang_enabled' => false],
            'pages'          => ['app_home' => ['title' => 'Home', 'path' => '/', 'robots' => 'index, follow, max-image-preview:large']],
        ];
        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        self::assertSame('noindex,nofollow', $this->createResolver($config, $request)->resolve()->robots);
    }

    public function testProviderIndexableFalseOverridesProviderAndPageRobots(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => ['site_name' => 'Site', 'title_template' => '{title}', 'hreflang_enabled' => false],
            'pages'          => ['app_home' => ['title' => 'Home', 'path' => '/', 'robots' => 'index, follow']],
        ];
        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        $first = new class implements SeoDefaultsProviderInterface {
            public function getDefaults(): array
            {
                return ['robots' => 'index, follow'];
            }
        };
        $second = new class implements SeoDefaultsProviderInterface {
            public function getDefaults(): array
            {
                return ['indexable' => false];
            }
        };

        self::assertSame('noindex,nofollow', $this->createResolver($config, $request, null, [$first, $second])->resolve()->robots);
    }

    public function testIndexableFalseKeepsExistingNoindexDirectives(): void
    {
        $config = [
            'enabled'        => true,
            'indexable'      => false,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => ['site_name' => 'Site', 'title_template' => '{title}', 'hreflang_enabled' => false],
            'pages'          => ['app_home' => ['title' => 'Home', 'path' => '/', 'robots' => 'NOINDEX, noarchive']],
        ];
        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        self::assertSame('NOINDEX, noarchive', $this->createResolver($config, $request)->resolve()->robots);
    }

    public function testRawJsonLdCannotCloseTheScriptElement(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => [
                'site_name'        => 'Site',
                'title_template'   => '{title}',
                'hreflang_enabled' => false,
                'json_ld'          => ['enabled' => true, 'json' => '{"name":"</script><script>alert(1)</script>"}'],
            ],
            'pages' => ['app_home' => ['title' => 'Home', 'path' => '/']],
        ];
        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        $json = $this->createResolver($config, $request)->resolve()->jsonLd['json'];

        self::assertIsString($json);
        self::assertStringNotContainsString('</', $json);
        // Still valid JSON with the same value ("\/" is an escaped slash).
        self::assertSame(['name' => '</script><script>alert(1)</script>'], json_decode($json, true));
    }

    public function testResolveSkipsAlternatesWhenUrlGeneratorFails(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en', 'es'],
            'defaults'       => [
                'site_name'          => '',
                'title_template'     => '{title}',
                'hreflang_enabled'   => true,
                'x_default_hreflang' => false,
            ],
            'pages' => [
                'app_dynamic' => ['title' => 'Dynamic'],
            ],
        ];

        $request = Request::create('/dynamic');
        $request->attributes->set('_route', 'app_dynamic');
        $request->attributes->set('_locale', 'en');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $params = []): string {
                if (($params['_locale'] ?? null) === 'es') {
                    throw new RuntimeException('missing locale route');
                }

                return '/generated/' . $route;
            },
        );

        $stack = new RequestStack();
        $stack->push($request);

        $resolver = new SeoMetadataResolver(
            $config,
            $stack,
            new SeoRuntime(),
            new SeoTemplateRenderer(),
            new SeoPathBuilder($config),
            $urlGenerator,
        );

        $metadata  = $resolver->resolve();
        $hreflangs = array_column($metadata->alternates, 'hreflang');

        $this->assertSame(['en'], $hreflangs);
    }

    public function testResolveIgnoresInvalidControllerAndMissingClassForAttributes(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => ['site_name' => '', 'title_template' => '{title}'],
            'pages'          => ['app_home' => ['title' => 'Home', 'path' => '/']],
        ];

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $request->attributes->set('_controller', 'invalid-controller');

        $metadata = $this->createResolver($config, $request)->resolve();
        $this->assertSame('Home', $metadata->title);

        $request->attributes->set('_controller', 'Nowo\\Missing\\Class::index');
        $metadata = $this->createResolver($config, $request)->resolve();
        $this->assertSame('Home', $metadata->title);
    }

    public function testResolveAppliesNoindexWhenNotIndexable(): void
    {
        $config = [
            'enabled'        => true,
            'indexable'      => false,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => ['site_name' => 'Site', 'title_template' => '{title}{separator}{site_name}', 'title_separator' => ' | '],
            'pages'          => ['app_home' => ['title' => 'Home', 'path' => '/']],
        ];
        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        $metadata = $this->createResolver($config, $request)->resolve();
        $this->assertSame('noindex,nofollow', $metadata->robots);
    }

    public function testResolveMergesVerificationAndOpenGraphExtras(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'base_url'       => 'https://example.com',
            'defaults'       => [
                'site_name'        => 'Site',
                'title_template'   => '{title}',
                'hreflang_enabled' => false,
                'verification'     => ['google' => 'g-token', 'bing' => 'b-token'],
                'open_graph'       => [
                    'enabled'           => true,
                    'type'              => 'website',
                    'image'             => '/og.png',
                    'image_width'       => 1200,
                    'image_height'      => 630,
                    'image_alt'         => 'Alt text',
                    'locale_alternates' => ['es-ES'],
                ],
                'twitter' => ['enabled' => false, 'card' => 'summary'],
                'json_ld' => ['enabled' => false],
            ],
            'pages' => ['app_home' => ['title' => 'Home', 'path' => '/']],
        ];
        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        $metadata = $this->createResolver($config, $request)->resolve();
        $this->assertSame('g-token', $metadata->verification['google']);
        $this->assertSame('b-token', $metadata->verification['bing']);
        $this->assertSame(1200, $metadata->openGraph['image_width']);
        $this->assertSame(630, $metadata->openGraph['image_height']);
        $this->assertSame('Alt text', $metadata->openGraph['image_alt']);
        $this->assertSame(['es_ES'], $metadata->openGraph['locale_alternates']);
    }

    public function testResolveUsesDefaultsProviderAndTitleFinal(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => [
                'site_name'        => 'YAML',
                'title_template'   => '{title}{separator}{site_name}',
                'title_separator'  => ' | ',
                'hreflang_enabled' => false,
                'json_ld'          => ['enabled' => false],
            ],
            'pages' => ['app_home' => ['title' => 'Home', 'path' => '/']],
        ];
        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        $provider = new class implements SeoDefaultsProviderInterface {
            public function getDefaults(): array
            {
                return [
                    'site_name'    => 'From DB',
                    'verification' => ['google' => 'from-provider'],
                ];
            }
        };

        $runtime = new SeoRuntime();
        $runtime->set(['title' => 'Final Title', 'title_final' => true]);

        $metadata = $this->createResolver($config, $request, $runtime, [$provider])->resolve();
        $this->assertSame('Final Title', $metadata->title);
        $this->assertSame('from-provider', $metadata->verification['google']);
        $this->assertSame('From DB', $metadata->openGraph['site_name']);
    }

    public function testResolveUsesPreencodedJsonLd(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => [
                'site_name'        => 'Site',
                'title_template'   => '{title}',
                'hreflang_enabled' => false,
                'json_ld'          => ['enabled' => true, 'json' => '{"@context":"https://schema.org","@type":"WebSite"}'],
            ],
            'pages' => ['app_home' => ['title' => 'Home', 'path' => '/']],
        ];
        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        $metadata = $this->createResolver($config, $request)->resolve();
        $this->assertSame('{"@context":"https://schema.org","@type":"WebSite"}', $metadata->jsonLd['json']);
        $this->assertSame([], $metadata->jsonLd['graph']);
    }

    public function testResolveUsesJsonLdDocumentAndRuntimeAlternates(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => [
                'site_name'        => 'Site',
                'title_template'   => '{title}',
                'hreflang_enabled' => true,
                'json_ld'          => ['enabled' => true],
            ],
            'pages' => ['app_home' => ['title' => 'Home', 'path' => '/']],
        ];
        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        $runtime = new SeoRuntime();
        $runtime->set([
            'alternates' => [
                'es' => 'https://example.com/es',
                ['url' => 'https://example.com/fr', 'hreflang' => 'fr', 'locale' => 'fr'],
                ['url' => 'https://example.com/de', 'hreflang' => 'de'],
                42,
            ],
            'json_ld' => [
                'enabled'  => true,
                'document' => ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'Demo'],
            ],
        ]);

        $metadata = $this->createResolver($config, $request, $runtime)->resolve();
        $this->assertCount(3, $metadata->alternates);
        $this->assertSame(['es', 'fr', 'de'], array_column($metadata->alternates, 'hreflang'));
        $this->assertSame('Organization', $metadata->jsonLd['document']['@type'] ?? null);
        $this->assertSame([], $metadata->jsonLd['graph']);
    }

    public function testResolveUsesJsonLdGraphFromDefaultsProvider(): void
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'defaults'       => [
                'site_name'        => 'Site',
                'title_template'   => '{title}',
                'hreflang_enabled' => false,
                'json_ld'          => ['enabled' => true],
            ],
            'pages' => ['app_home' => ['title' => 'Home', 'path' => '/']],
        ];
        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');

        $provider = new class implements SeoDefaultsProviderInterface {
            public function getDefaults(): array
            {
                return [
                    'indexable' => false,
                    'json_ld'   => [
                        'enabled' => true,
                        'graph'   => [
                            ['@type' => 'WebSite', 'name' => 'From provider'],
                            'skip',
                        ],
                    ],
                ];
            }
        };

        $metadata = $this->createResolver($config, $request, null, [$provider])->resolve();
        $this->assertSame('noindex,nofollow', $metadata->robots);
        $this->assertCount(1, $metadata->jsonLd['graph']);
        $this->assertSame('WebSite', $metadata->jsonLd['graph'][0]['@type']);
    }
}

final class SeoPlainFixtureController
{
}
