<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Twig;

use Nowo\SeoKitBundle\Service\SeoMetadataResolver;
use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use Nowo\SeoKitBundle\Service\SeoRuntime;
use Nowo\SeoKitBundle\Service\SeoTemplateRenderer;
use Nowo\SeoKitBundle\Twig\SeoKitExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class SeoKitExtensionTest extends TestCase
{
    public function testRenderHead(): void
    {
        $config  = $this->minimalConfig();
        $stack   = new RequestStack();
        $request = Request::create('/');
        $request->attributes->set('_route', 'app_home');
        $request->setLocale('en');
        $stack->push($request);

        $resolver = new SeoMetadataResolver(
            $config,
            $stack,
            new SeoRuntime(),
            new SeoTemplateRenderer(),
            new SeoPathBuilder($config),
            $this->createMock(UrlGeneratorInterface::class),
        );

        $twig = new Environment(new ArrayLoader([
            'head.twig' => '<title>{{ seo.title }}</title>',
        ]));
        $ext = new SeoKitExtension(true, $resolver, $twig, ['head' => 'head.twig']);

        self::assertStringContainsString('Home', $ext->metadata()->title);
        self::assertStringContainsString('<title>', $ext->renderHead());
        self::assertCount(3, $ext->getFunctions());
    }

    public function testDisabledHeadIsEmpty(): void
    {
        $config   = $this->minimalConfig();
        $stack    = new RequestStack();
        $resolver = new SeoMetadataResolver(
            $config,
            $stack,
            new SeoRuntime(),
            new SeoTemplateRenderer(),
            new SeoPathBuilder($config),
            $this->createMock(UrlGeneratorInterface::class),
        );
        $twig = new Environment(new ArrayLoader([]));
        $ext  = new SeoKitExtension(false, $resolver, $twig, ['head' => 'head.twig']);
        self::assertSame('', $ext->renderHead());
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalConfig(): array
    {
        return [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'base_url'       => 'https://example.com',
            'defaults'       => [
                'site_name'          => 'Site',
                'title_separator'    => ' | ',
                'title_template'     => '{title}{separator}{site_name}',
                'canonical_enabled'  => true,
                'hreflang_enabled'   => false,
                'x_default_hreflang' => false,
                'open_graph'         => ['enabled' => false, 'type' => 'website', 'image' => null, 'site_name' => null],
                'twitter'            => ['enabled' => false, 'card' => 'summary', 'site' => null, 'creator' => null, 'image' => null],
                'json_ld'            => ['enabled' => false, 'organization' => [], 'extra' => []],
            ],
            'pages' => [
                'app_home' => ['title' => 'Home', 'path' => '/'],
            ],
            'slug_routes' => [],
            'slugs'       => [],
        ];
    }
}
