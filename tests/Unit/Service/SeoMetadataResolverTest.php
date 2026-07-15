<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Service\SeoMetadataResolver;
use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use Nowo\SeoKitBundle\Service\SeoRuntime;
use Nowo\SeoKitBundle\Service\SeoTemplateRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SeoMetadataResolverTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function createResolver(array $config, ?Request $request = null, ?SeoRuntime $runtime = null): SeoMetadataResolver
    {
        $requestStack = new RequestStack();
        if ($request !== null) {
            $requestStack->push($request);
        }

        $runtime ??= new SeoRuntime();
        $paths = new SeoPathBuilder($config);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $params = []) => '/generated/'.$route.'?'.http_build_query($params),
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
            'enabled' => true,
            'default_locale' => 'en',
            'locales' => ['en', 'es'],
            'defaults' => [
                'site_name' => 'Demo',
                'title_separator' => ' | ',
                'title_template' => '{title}{separator}{site_name}',
                'description' => 'Default description',
            ],
            'slug_routes' => [
                'app_blog_show' => [
                    'slug_parameter' => 'slug',
                    'title_template' => 'Blog: {slug}',
                    'description_template' => 'Post {slug}',
                    'path_pattern' => '/blog/{slug}',
                ],
            ],
            'pages' => [
                'app_blog_show' => [
                    'title' => 'Blog',
                    'robots' => 'index,follow',
                ],
            ],
            'slugs' => [
                'app_blog_show' => [
                    'hello' => [
                        'title' => 'Hello post',
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
            'enabled' => true,
            'default_locale' => 'en',
            'locales' => ['en'],
            'defaults' => ['site_name' => ''],
            'slug_routes' => [
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
            'enabled' => true,
            'default_locale' => 'en',
            'locales' => ['en'],
            'defaults' => [
                'site_name' => 'Demo',
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
            'enabled' => true,
            'default_locale' => 'en',
            'locales' => ['en', 'es'],
            'defaults' => [
                'site_name' => '',
                'hreflang_enabled' => true,
                'x_default_hreflang' => true,
            ],
            'pages' => [
                'app_home' => [
                    'title' => 'Home',
                    'path' => '/',
                    'locales' => ['es' => ['path' => '/es']],
                ],
            ],
        ];

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $request->attributes->set('_locale', 'en');

        $metadata = $this->createResolver($config, $request)->resolve();
        $hreflangs = array_column($metadata->alternates, 'hreflang');

        $this->assertContains('en', $hreflangs);
        $this->assertContains('es', $hreflangs);
        $this->assertContains('x-default', $hreflangs);
    }

    public function testResolveStripsTrailingSeparatorWhenSiteNameEmpty(): void
    {
        $config = [
            'enabled' => true,
            'default_locale' => 'en',
            'locales' => ['en'],
            'defaults' => [
                'site_name' => '',
                'title_separator' => ' | ',
                'title_template' => '{title}{separator}{site_name}',
            ],
            'pages' => ['app_home' => ['title' => 'Only title', 'path' => '/']],
        ];

        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $request->attributes->set('title', 'Only title');

        $metadata = $this->createResolver($config, $request)->resolve();

        $this->assertSame('Only title', $metadata->title);
    }
}
