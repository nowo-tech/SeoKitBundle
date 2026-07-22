<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\DependencyInjection;

use Nowo\SeoKitBundle\DependencyInjection\Configuration;
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
        $this->assertSame('en', $config['default_locale']);
        $this->assertSame(['en', 'es', 'fr', 'de', 'it', 'pt', 'nl'], $config['locales']);
        $this->assertSame(' | ', $config['defaults']['title_separator']);
        $this->assertTrue($config['sitemap']['enabled']);
        $this->assertSame('/sitemap.xml', $config['sitemap']['path']);
        $this->assertTrue($config['robots']['enabled']);
        $this->assertSame('/robots.txt', $config['robots']['path']);
        $this->assertSame('@NowoSeoKitBundle/seo/head.html.twig', $config['templates']['head']);
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
}
