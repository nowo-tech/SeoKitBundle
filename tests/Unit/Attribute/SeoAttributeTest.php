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
            'pages'       => [],
            'slug_routes' => [],
            'slugs'       => [],
        ];

        $stack   = new RequestStack();
        $request = Request::create('/contact');
        $request->attributes->set('_route', 'app_contact');
        $request->attributes->set('_controller', SeoAttributeFixtureController::class . '::index');
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

    public function testAttributeAppliesNoindexOpenGraphAndTwitter(): void
    {
        $config = [
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
                'open_graph'         => ['enabled' => true, 'type' => 'website', 'image' => null, 'site_name' => null],
                'twitter'            => ['enabled' => true, 'card' => 'summary', 'site' => null, 'creator' => null, 'image' => null],
                'json_ld'            => ['enabled' => false, 'organization' => [], 'extra' => []],
            ],
            'pages'       => [],
            'slug_routes' => [],
            'slugs'       => [],
        ];

        $stack   = new RequestStack();
        $request = Request::create('/private');
        $request->attributes->set('_route', 'app_private');
        $request->attributes->set('_controller', SeoAttributeSocialFixtureController::class . '::show');
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

        self::assertSame('noindex,nofollow', $seo->robots);
        self::assertSame('product', $seo->openGraph['type']);
        self::assertSame('https://example.com/private-og.png', $seo->openGraph['image']);
        self::assertSame('https://example.com/private-twitter.png', $seo->twitter['image']);
        self::assertSame('@creator', $seo->twitter['creator']);
    }
}

#[Seo(title: 'Contact Us', description: 'Get in touch')]
final class SeoAttributeFixtureController
{
    public function index(): int
    {
        return 0;
    }
}

final class SeoAttributeSocialFixtureController
{
    #[Seo(title: 'Private page', openGraph: ['type' => 'product', 'image' => '/private-og.png'], twitter: ['image' => '/private-twitter.png', 'creator' => '@creator'], noindex: true)]
    public function show(): int
    {
        return 0;
    }
}
