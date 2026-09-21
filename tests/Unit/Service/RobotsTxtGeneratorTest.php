<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Service\RobotsTxtGenerator;
use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use Nowo\SeoKitBundle\Service\SiteIndexabilityProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class RobotsTxtGeneratorTest extends TestCase
{
    public function testGenerateIncludesAllowDisallowAndSitemap(): void
    {
        $config = [
            'base_url' => 'https://example.com',
            'robots'   => [
                'user_agent'   => '*',
                'allow'        => ['/'],
                'disallow'     => ['/admin'],
                'sitemap_link' => true,
            ],
            'sitemap' => [
                'enabled' => true,
                'path'    => '/sitemap.xml',
            ],
        ];
        $generator = new RobotsTxtGenerator($config, new SeoPathBuilder($config));
        $request   = Request::create('https://example.com/');

        $output = $generator->generate($request);

        $this->assertStringContainsString('User-agent: *', $output);
        $this->assertStringContainsString('Allow: /', $output);
        $this->assertStringContainsString('Disallow: /admin', $output);
        $this->assertStringContainsString('Sitemap: https://example.com/sitemap.xml', $output);
    }

    public function testGenerateOmitsSitemapWhenDisabled(): void
    {
        $config = [
            'robots'  => ['sitemap_link' => true],
            'sitemap' => ['enabled' => false, 'path' => '/sitemap.xml'],
        ];
        $generator = new RobotsTxtGenerator($config, new SeoPathBuilder($config));
        $request   = Request::create('https://example.com/');

        $this->assertStringNotContainsString('Sitemap:', $generator->generate($request));
    }

    public function testGenerateDisallowsAllWhenNotIndexable(): void
    {
        $config = [
            'indexable' => false,
            'base_url'  => 'https://example.com',
            'robots'    => [
                'user_agent'   => '*',
                'allow'        => ['/'],
                'disallow'     => [],
                'sitemap_link' => true,
            ],
            'sitemap' => [
                'enabled' => true,
                'path'    => '/sitemap.xml',
            ],
        ];
        $generator = new RobotsTxtGenerator($config, new SeoPathBuilder($config));
        $request   = Request::create('https://example.com/');

        $output = $generator->generate($request);

        $this->assertStringContainsString('Disallow: /', $output);
        $this->assertStringNotContainsString('Allow:', $output);
        $this->assertFalse($generator->isIndexable());
    }

    public function testIndexabilityProviderCanForceNonIndexable(): void
    {
        $config = [
            'indexable' => true,
            'robots'    => ['allow' => ['/'], 'sitemap_link' => false],
            'sitemap'   => ['enabled' => true],
        ];
        $provider = new class implements SiteIndexabilityProviderInterface {
            public function isIndexable(): bool
            {
                return false;
            }
        };
        $generator = new RobotsTxtGenerator($config, new SeoPathBuilder($config), [$provider]);

        $this->assertFalse($generator->isIndexable());
        $this->assertStringContainsString('Disallow: /', $generator->generate(Request::create('/')));
    }
}
