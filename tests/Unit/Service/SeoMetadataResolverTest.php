<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Service\SeoMetadataResolver;
use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use Nowo\SeoKitBundle\Service\SeoRuntime;
use Nowo\SeoKitBundle\Service\SeoTemplateRenderer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use const PHP_URL_PATH;

final class SeoMetadataResolverTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function createResolver(array $config, ?Request $request = null, ?SeoRuntime $runtime = null): SeoMetadataResolver
    {
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
        $request->attributes->set('_route_params', ['id' => 7]);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $params = []): string => '/generated/' . $route . '/' . ($params['_locale'] ?? 'en'),
        );

        $resolver = new SeoMetadataResolver(
            $config,
            (static function () use ($request): RequestStack {
                $stack = new RequestStack();
                $stack->push($request);

                return $stack;
            })(),
            new SeoRuntime(),
            new SeoTemplateRenderer(),
            new SeoPathBuilder($config),
            $urlGenerator,
        );

        $metadata  = $resolver->resolve();
        $hreflangs = array_column($metadata->alternates, 'hreflang');

        $this->assertContains('en', $hreflangs);
        $this->assertContains('es', $hreflangs);
        $this->assertSame('/dynamic', parse_url($metadata->alternates[0]['url'], PHP_URL_PATH));
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
}

final class SeoPlainFixtureController
{
}
