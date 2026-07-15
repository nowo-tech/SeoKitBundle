<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Attribute;

use Nowo\SeoKitBundle\Attribute\Seo;
use Nowo\SeoKitBundle\Service\SeoMetadataResolver;
use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use Nowo\SeoKitBundle\Service\SeoRuntime;
use Nowo\SeoKitBundle\Service\SeoTemplateRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SeoAttributeTest extends TestCase
{
    public function testAttributeOverridesTitle(): void
    {
        $config = [
            'enabled' => true,
            'default_locale' => 'en',
            'locales' => ['en'],
            'base_url' => 'https://example.com',
            'defaults' => [
                'site_name' => 'Site',
                'title_separator' => ' | ',
                'title_template' => '{title}{separator}{site_name}',
                'canonical_enabled' => true,
                'hreflang_enabled' => false,
                'x_default_hreflang' => false,
                'open_graph' => ['enabled' => false, 'type' => 'website', 'image' => null, 'site_name' => null],
                'twitter' => ['enabled' => false, 'card' => 'summary', 'site' => null, 'creator' => null, 'image' => null],
                'json_ld' => ['enabled' => false, 'organization' => [], 'extra' => []],
            ],
            'pages' => [],
            'slug_routes' => [],
            'slugs' => [],
        ];

        $stack = new RequestStack();
        $request = Request::create('/contact');
        $request->attributes->set('_route', 'app_contact');
        $request->attributes->set('_controller', SeoAttributeFixtureController::class.'::index');
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

        $seo = $resolver->resolve();
        self::assertStringContainsString('Contact Us', $seo->title);
        self::assertSame('attribute', $seo->source);
    }
}

final class SeoAttributeFixtureController
{
    #[Seo(title: 'Contact Us', description: 'Get in touch')]
    public function index(): void
    {
    }
}
