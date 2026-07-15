<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SeoPathBuilderTest extends TestCase
{
    public function testAbsoluteUrlUsesBaseUrlWhenConfigured(): void
    {
        $builder = new SeoPathBuilder(['base_url' => 'https://example.com']);
        $request = Request::create('http://localhost/foo');

        $this->assertSame('https://example.com/about', $builder->absoluteUrl($request, '/about'));
    }

    public function testAbsoluteUrlPassesThroughAbsolutePaths(): void
    {
        $builder = new SeoPathBuilder([]);
        $request = Request::create('http://localhost/');

        $this->assertSame(
            'https://cdn.example.com/img.png',
            $builder->absoluteUrl($request, 'https://cdn.example.com/img.png'),
        );
    }

    public function testAbsoluteUrlUsesRequestHostWhenNoBaseUrl(): void
    {
        $builder = new SeoPathBuilder([]);
        $request = Request::create('https://app.test/en/home');

        $this->assertSame('https://app.test/en/home', $builder->absoluteUrl($request, '/en/home'));
    }

    public function testPagePathReturnsLocaleSpecificPath(): void
    {
        $config = [
            'default_locale' => 'en',
            'pages' => [
                'app_home' => [
                    'path' => '/',
                    'locales' => [
                        'es' => ['path' => '/es'],
                    ],
                ],
            ],
        ];
        $builder = new SeoPathBuilder($config);

        $this->assertSame('/es', $builder->pagePath('app_home', 'es'));
        $this->assertSame('/', $builder->pagePath('app_home', 'en'));
    }

    public function testPagePathReturnsFallbackWhenRouteMissing(): void
    {
        $builder = new SeoPathBuilder(['pages' => []]);

        $this->assertSame('/fallback', $builder->pagePath('missing', 'en', '/fallback'));
    }

    public function testSlugPathBuildsFromPatternAndTranslatedSlug(): void
    {
        $config = [
            'slug_routes' => [
                'app_blog_show' => [
                    'path_pattern' => '/blog/{slug}',
                    'locales' => [
                        'es' => ['path_pattern' => '/es/blog/{slug}'],
                    ],
                ],
            ],
            'slugs' => [
                'app_blog_show' => [
                    'hello-world' => [
                        'locales' => [
                            'es' => ['slug' => 'hola-mundo'],
                        ],
                    ],
                ],
            ],
        ];
        $builder = new SeoPathBuilder($config);

        $this->assertSame('/blog/hello-world', $builder->slugPath('app_blog_show', 'en', 'hello-world'));
        $this->assertSame('/es/blog/hola-mundo', $builder->slugPath('app_blog_show', 'es', 'hello-world'));
    }

    public function testSlugPathReturnsNullWhenSlugRouteMissing(): void
    {
        $config = [
            'slug_routes' => [],
            'slugs' => [
                'app_blog_show' => [
                    'custom' => ['path' => '/special/custom'],
                ],
            ],
        ];
        $builder = new SeoPathBuilder($config);

        $this->assertNull($builder->slugPath('app_blog_show', 'en', 'custom'));
    }

    public function testSlugPathPrefersPatternOverSpecificPath(): void
    {
        $config = [
            'slug_routes' => ['app_blog_show' => ['path_pattern' => '/blog/{slug}']],
            'slugs' => [
                'app_blog_show' => [
                    'custom' => ['path' => '/special/custom'],
                ],
            ],
        ];
        $builder = new SeoPathBuilder($config);

        $this->assertSame('/blog/custom', $builder->slugPath('app_blog_show', 'en', 'custom'));
    }
}
