<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Service\GeoRobotsGroupsProviderInterface;
use Nowo\SeoKitBundle\Service\RobotsTxtGenerator;
use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use Nowo\SeoKitBundle\Service\SiteIndexabilityProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

use function in_array;
use function is_int;

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

    public function testGenerateAppendsYamlAndProviderGroupsWhenIndexable(): void
    {
        $config = [
            'base_url' => 'https://example.com',
            'robots'   => [
                'user_agent'   => '*',
                'allow'        => ['/'],
                'disallow'     => ['/admin'],
                'sitemap_link' => false,
                'groups'       => [
                    ['user_agent' => 'GPTBot', 'allow' => ['/'], 'disallow' => []],
                ],
            ],
            'sitemap' => ['enabled' => false],
        ];
        $provider = new class implements GeoRobotsGroupsProviderInterface {
            public function groups(): array
            {
                return [
                    ['user_agent' => 'Google-Extended', 'allow' => [], 'disallow' => ['/']],
                ];
            }
        };
        $generator = new RobotsTxtGenerator($config, new SeoPathBuilder($config), [], [$provider]);
        $output    = $generator->generate(Request::create('https://example.com/'));

        $this->assertStringContainsString("User-agent: *\nAllow: /\nDisallow: /admin", $output);
        $this->assertStringContainsString("User-agent: GPTBot\nAllow: /", $output);
        $this->assertStringContainsString("User-agent: Google-Extended\nDisallow: /", $output);
    }

    public function testGenerateOmitsExtraGroupsWhenNotIndexable(): void
    {
        $config = [
            'indexable' => false,
            'robots'    => [
                'groups' => [
                    ['user_agent' => 'GPTBot', 'allow' => ['/']],
                ],
                'sitemap_link' => false,
            ],
            'sitemap' => ['enabled' => false],
        ];
        $output = (new RobotsTxtGenerator($config, new SeoPathBuilder($config)))
            ->generate(Request::create('/'));

        $this->assertStringNotContainsString('GPTBot', $output);
        $this->assertStringContainsString('Disallow: /', $output);
    }

    public function testGenerateIgnoresNonArrayYamlGroups(): void
    {
        $config = [
            'robots' => [
                'allow'  => ['/'],
                'groups' => [
                    'nope',
                    ['user_agent' => ''],
                    ['user_agent' => 1],
                ],
                'sitemap_link' => false,
            ],
            'sitemap' => ['enabled' => false],
        ];
        $output = (new RobotsTxtGenerator($config, new SeoPathBuilder($config)))
            ->generate(Request::create('/'));

        $this->assertStringContainsString('User-agent: *', $output);
        $this->assertStringNotContainsString('nope', $output);
    }

    /**
     * RI3-L1 sink: a host groups provider (e.g. fed from a database) must not add its own robots.txt lines.
     */
    public function testGenerateDropsValuesWithLineBreaksOrControlCharacters(): void
    {
        $config = [
            'robots' => [
                'user_agent'   => "*\nDisallow: /",
                'allow'        => ['/', "/a\r\nDisallow: /"],
                'disallow'     => ['/admin', "/b\x00"],
                'sitemap_link' => false,
            ],
            'sitemap' => ['enabled' => false],
        ];
        $provider = new class implements GeoRobotsGroupsProviderInterface {
            public function groups(): array
            {
                return [
                    ['user_agent' => "GPTBot\nUser-agent: *\nDisallow: /", 'allow' => ['/'], 'disallow' => []],
                    ['user_agent' => "Evil\u{2028}Bot", 'allow' => ['/'], 'disallow' => []],
                    ['user_agent' => "Bad\xC3", 'allow' => ['/'], 'disallow' => []],
                    ['user_agent' => 'GoodBot', 'allow' => ['/docs', "/x\rDisallow: /"], 'disallow' => ["/y\nAllow: /", '/private']],
                ];
            }
        };
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(8))
            ->method('warning')
            ->with($this->stringContains('line break or control character'), $this->callback(
                static fn (array $context): bool => in_array($context['kind'] ?? null, ['user agent', 'path'], true) && is_int($context['length'] ?? null),
            ));

        $output = (new RobotsTxtGenerator($config, new SeoPathBuilder($config), [], [$provider], $logger))
            ->generate(Request::create('https://example.com/'));

        $this->assertSame(
            "User-agent: *\nAllow: /\nDisallow: /admin\n\nUser-agent: GoodBot\nAllow: /docs\nDisallow: /private\n",
            $output,
        );
    }

    public function testGenerateDropsInvalidValuesWithoutALogger(): void
    {
        $config = [
            'robots'  => ['user_agent' => "Bot\nDisallow: /", 'allow' => ['/'], 'sitemap_link' => false],
            'sitemap' => ['enabled' => false],
        ];

        $output = (new RobotsTxtGenerator($config, new SeoPathBuilder($config)))->generate(Request::create('/'));

        $this->assertSame("User-agent: *\nAllow: /\n", $output);
    }
}
