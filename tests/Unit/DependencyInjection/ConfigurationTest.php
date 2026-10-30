<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\DependencyInjection;

use Nowo\SeoKitBundle\DependencyInjection\Configuration;
use Nowo\SeoKitBundle\Ui\SeoKitPages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    private Processor $processor;

    protected function setUp(): void
    {
        $this->processor = new Processor();
    }

    public function testProcessConfigurationDefaults(): void
    {
        $config = $this->processor->processConfiguration(new Configuration(), [[]]);

        $this->assertTrue($config['enabled']);
        $this->assertTrue($config['indexable']);
        $this->assertSame('en', $config['default_locale']);
        $this->assertSame(['en', 'es', 'fr', 'de', 'it', 'pt', 'nl'], $config['locales']);
        $this->assertSame(' | ', $config['defaults']['title_separator']);
        $this->assertNull($config['defaults']['verification']['google']);
        $this->assertNull($config['defaults']['verification']['bing']);
        $this->assertTrue($config['sitemap']['enabled']);
        $this->assertSame('/sitemap.xml', $config['sitemap']['path']);
        $this->assertTrue($config['robots']['enabled']);
        $this->assertSame('/robots.txt', $config['robots']['path']);
        $this->assertSame('@NowoSeoKitBundle/seo/head.html.twig', $config['templates']['head']);
        $this->assertTrue($config['templates']['head_includes_title']);
        $this->assertNull($config['defaults']['open_graph']['image_width']);
        $this->assertSame([], $config['defaults']['open_graph']['locale_alternates']);
    }

    public function testProcessConfigurationMergesPagesAndSlugRoutes(): void
    {
        $input = [
            'pages' => [
                'app_home' => [
                    'title'   => 'Home',
                    'path'    => '/',
                    'locales' => [
                        'es' => ['title' => 'Inicio', 'path' => '/es'],
                    ],
                ],
            ],
            'slug_routes' => [
                'app_blog_show' => [
                    'slug_parameter' => 'slug',
                    'title_template' => '{title}',
                    'path_pattern'   => '/blog/{slug}',
                ],
            ],
            'slugs' => [
                'app_blog_show' => [
                    'my_post' => [
                        'title'   => 'My Post',
                        'noindex' => false,
                    ],
                ],
            ],
        ];

        $config = $this->processor->processConfiguration(new Configuration(), [$input]);

        $this->assertSame('Home', $config['pages']['app_home']['title']);
        $this->assertSame('/es', $config['pages']['app_home']['locales']['es']['path']);
        $this->assertSame('/blog/{slug}', $config['slug_routes']['app_blog_show']['path_pattern']);
        $this->assertArrayHasKey('app_blog_show', $config['slugs']);
        $this->assertArrayHasKey('my_post', $config['slugs']['app_blog_show']);
        $this->assertSame('My Post', $config['slugs']['app_blog_show']['my_post']['title'] ?? null);
    }

    public function testSitemapPriorityMustBeBetweenZeroAndOne(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processor->processConfiguration(new Configuration(), [[
            'pages' => [
                'app_home' => ['sitemap_priority' => 1.5],
            ],
        ]]);
    }

    public function testSlugKeysPreserveHyphens(): void
    {
        $config = $this->processor->processConfiguration(new Configuration(), [[
            'slugs' => [
                'app_blog_show' => [
                    'hello-world' => [
                        'title'   => 'Hello',
                        'locales' => [
                            'es' => ['slug' => 'hola-mundo'],
                        ],
                    ],
                ],
            ],
        ]]);

        $this->assertArrayHasKey('hello-world', $config['slugs']['app_blog_show']);
        $this->assertArrayNotHasKey('hello_world', $config['slugs']['app_blog_show']);
        $this->assertSame('hola-mundo', $config['slugs']['app_blog_show']['hello-world']['locales']['es']['slug']);
    }

    public function testWebUiAndSecurityDefaults(): void
    {
        $config = $this->processor->processConfiguration(new Configuration(), [[]]);

        $this->assertSame([
            'enabled'             => false,
            'settings'            => true,
            'surfaces'            => true,
            'path_prefix'         => null,
            'layout_template'     => null,
            'templates'           => SeoKitPages::TEMPLATES,
            'form_themes'         => [SeoKitPages::DEFAULT_FORM_THEME],
            'api_enabled'         => true,
            'api_csrf_protection' => true,
        ], $config['web_ui']);
        $this->assertSame(['access_roles' => ['ROLE_ADMIN'], 'access_checker' => null, 'allow_unauthenticated' => false], $config['security']);
        $this->assertNull($config['admin']['enabled']);
        $this->assertNull($config['admin']['role']);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function invalidWebUiConfigs(): iterable
    {
        yield 'path prefix without slash' => [['web_ui' => ['path_prefix' => 'admin/seo']]];
        yield 'path prefix with query' => [['web_ui' => ['path_prefix' => '/admin/seo?x=1']]];
        yield 'path prefix dot-dot' => [['web_ui' => ['path_prefix' => '/..']]];
        yield 'path prefix dot' => [['web_ui' => ['path_prefix' => '/.']]];
        yield 'path prefix inner dot-dot' => [['web_ui' => ['path_prefix' => '/a/../b']]];
        yield 'path prefix trailing dot' => [['web_ui' => ['path_prefix' => '/a/.']]];
        yield 'empty page template' => [['web_ui' => ['templates' => ['settings' => '']]]];
        yield 'public role' => [['security' => ['access_roles' => ['PUBLIC_ACCESS']]]];
        yield 'anonymous role' => [['security' => ['access_roles' => ['IS_AUTHENTICATED_ANONYMOUSLY']]]];
        yield 'blank role' => [['security' => ['access_roles' => [' ']]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidWebUiConfigs')]
    public function testInvalidWebUiAndSecurityValuesAreRejected(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->processor->processConfiguration(new Configuration(), [$config]);
    }

    public function testValidPathPrefixIsKept(): void
    {
        foreach (['/admin/seo-kit', '/a.b/.well-known-seo', '/a/...'] as $prefix) {
            $config = $this->processor->processConfiguration(new Configuration(), [['web_ui' => ['path_prefix' => $prefix]]]);

            $this->assertSame($prefix, $config['web_ui']['path_prefix']);
        }
    }
}
