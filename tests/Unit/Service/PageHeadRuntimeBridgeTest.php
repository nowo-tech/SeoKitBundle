<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Model\HreflangSet;
use Nowo\SeoKitBundle\Model\PageHead;
use Nowo\SeoKitBundle\Model\StructuredData\OrganizationNode;
use Nowo\SeoKitBundle\Model\StructuredData\StructuredDataGraph;
use Nowo\SeoKitBundle\Service\PageHeadRuntimeBridge;
use Nowo\SeoKitBundle\Service\SeoRuntime;
use PHPUnit\Framework\TestCase;

final class PageHeadRuntimeBridgeTest extends TestCase
{
    public function testApplyMapsPageHeadIntoSeoRuntimeIncludingXDefaultAndJsonLd(): void
    {
        $runtime = new SeoRuntime();
        $bridge  = new PageHeadRuntimeBridge($runtime);

        $bridge->apply(new PageHead(
            title: 'About',
            description: 'About us',
            canonical: 'https://nowo.tech/about',
            robots: 'index,follow',
            hreflang: new HreflangSet(
                ['es' => 'https://nowo.tech/about', 'en' => 'https://nowo.tech/en/about'],
                'https://nowo.tech/about',
            ),
            ogSiteName: 'Nowo',
            ogType: 'website',
            ogTitle: 'About',
            ogDescription: 'About us',
            ogImage: 'https://nowo.tech/og.png',
            ogImageWidth: 1200,
            ogImageHeight: 630,
            ogLocale: 'es_ES',
            ogLocaleAlternates: ['en_GB'],
            twitterCard: 'summary_large_image',
            structuredData: new StructuredDataGraph(new OrganizationNode('Nowo', 'https://nowo.tech')),
            pathsByLocale: ['es' => '/about', 'en' => '/en/about'],
            breadcrumbs: [],
        ));

        $overrides = $runtime->getOverrides();
        self::assertSame('About', $overrides['title']);
        self::assertTrue($overrides['title_final']);
        self::assertSame('https://nowo.tech/about', $overrides['canonical']);
        self::assertCount(3, $overrides['alternates']);
        self::assertSame('x-default', $overrides['alternates'][2]['hreflang']);
        self::assertSame('summary_large_image', $overrides['twitter']['card']);
        self::assertTrue($overrides['json_ld']['enabled']);
        self::assertNotSame('', $overrides['json_ld']['json']);
    }

    public function testApplyOmitsJsonLdAndXDefaultWhenEmpty(): void
    {
        $runtime = new SeoRuntime();
        (new PageHeadRuntimeBridge($runtime))->apply(new PageHead(
            title: 'Solo',
            description: '',
            canonical: 'https://nowo.tech/solo',
            robots: 'index,follow',
            hreflang: new HreflangSet(['es' => 'https://nowo.tech/solo'], null),
            ogSiteName: 'Nowo',
            ogType: 'website',
            ogTitle: 'Solo',
            ogDescription: '',
            ogImage: null,
            ogImageWidth: null,
            ogImageHeight: null,
            ogLocale: 'es_ES',
            ogLocaleAlternates: [],
            twitterCard: 'summary',
            structuredData: new StructuredDataGraph(),
            pathsByLocale: ['es' => '/solo'],
            breadcrumbs: [],
        ));

        $overrides = $runtime->getOverrides();
        self::assertCount(1, $overrides['alternates']);
        self::assertArrayNotHasKey('json_ld', $overrides);
        self::assertSame('summary', $overrides['twitter']['card']);
    }
}
