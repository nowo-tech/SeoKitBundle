<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Service\RobotsTxtGenerator;
use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class RobotsTxtGeneratorTest extends TestCase
{
    public function testGenerateIncludesAllowDisallowAndSitemap(): void
    {
        $config = [
            'base_url' => 'https://example.com',
            'robots' => [
                'user_agent' => '*',
                'allow' => ['/'],
                'disallow' => ['/admin'],
                'sitemap_link' => true,
            ],
            'sitemap' => [
                'enabled' => true,
                'path' => '/sitemap.xml',
            ],
        ];
        $generator = new RobotsTxtGenerator($config, new SeoPathBuilder($config));
        $request = Request::create('https://example.com/');

        $output = $generator->generate($request);

        $this->assertStringContainsString('User-agent: *', $output);
        $this->assertStringContainsString('Allow: /', $output);
        $this->assertStringContainsString('Disallow: /admin', $output);
        $this->assertStringContainsString('Sitemap: https://example.com/sitemap.xml', $output);
    }

    public function testGenerateOmitsSitemapWhenDisabled(): void
    {
        $config = [
            'robots' => ['sitemap_link' => true],
            'sitemap' => ['enabled' => false, 'path' => '/sitemap.xml'],
        ];
        $generator = new RobotsTxtGenerator($config, new SeoPathBuilder($config));
        $request = Request::create('https://example.com/');

        $this->assertStringNotContainsString('Sitemap:', $generator->generate($request));
    }
}
