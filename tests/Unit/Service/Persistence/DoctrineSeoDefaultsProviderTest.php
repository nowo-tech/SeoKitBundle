<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service\Persistence;

use Nowo\SeoKitBundle\Model\SeoSiteConfig;
use Nowo\SeoKitBundle\Service\Persistence\DoctrineSeoDefaultsProvider;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProviderInterface;
use PHPUnit\Framework\TestCase;

final class DoctrineSeoDefaultsProviderTest extends TestCase
{
    public function testMapsConfigToDefaultsAndIndexability(): void
    {
        $config = new SeoSiteConfig(
            siteName: 'Nowo',
            titleTemplate: '%page% · %site%',
            indexable: true,
            robots: 'index, follow',
            openGraphImage: '/og.png',
            twitterSite: '@nowo',
            organisationLegalName: 'Nowo SL',
            organisationLogo: '/logo.png',
            contactEmail: 'hello@nowo.tech',
            contactPhone: '+34',
            streetAddress: 'Calle 1',
            postalCode: '08001',
            addressLocality: 'Barcelona',
            addressRegion: 'CAT',
            addressCountry: 'ES',
            socialProfiles: ['https://x.com/nowo'],
            googleSiteVerification: 'g',
            bingSiteVerification: 'b',
            byLocale: [],
        );

        $provider = new DoctrineSeoDefaultsProvider($this->configProvider($config));
        $defaults = $provider->getDefaults();

        self::assertTrue($provider->isIndexable());
        self::assertSame('Nowo', $defaults['site_name']);
        self::assertSame('{title} · {site_name}', $defaults['title_template']);
        self::assertSame('index, follow', $defaults['robots']);
        self::assertSame(['google' => 'g', 'bing' => 'b'], $defaults['verification']);
        self::assertSame('PostalAddress', $defaults['json_ld']['organization']['address']['@type']);
        self::assertSame(['https://x.com/nowo'], $defaults['json_ld']['organization']['sameAs']);
    }

    public function testNonIndexableForcesNoindexAndKeepsKitTitleTemplate(): void
    {
        $config = new SeoSiteConfig(
            siteName: 'Nowo',
            titleTemplate: '{title} | {site_name}',
            indexable: false,
            robots: 'index, follow',
            openGraphImage: null,
            twitterSite: null,
            organisationLegalName: null,
            organisationLogo: null,
            contactEmail: null,
            contactPhone: null,
            streetAddress: null,
            postalCode: null,
            addressLocality: null,
            addressRegion: null,
            addressCountry: null,
            socialProfiles: [],
            googleSiteVerification: null,
            bingSiteVerification: null,
            byLocale: [],
        );

        $defaults = (new DoctrineSeoDefaultsProvider($this->configProvider($config)))->getDefaults();

        self::assertSame('noindex, nofollow', $defaults['robots']);
        self::assertSame('{title} | {site_name}', $defaults['title_template']);
        self::assertSame('Nowo', $defaults['json_ld']['organization']['name']);
        self::assertArrayNotHasKey('address', $defaults['json_ld']['organization']);
    }

    private function configProvider(SeoSiteConfig $config): SeoSiteConfigProviderInterface
    {
        $provider = $this->createMock(SeoSiteConfigProviderInterface::class);
        $provider->method('get')->willReturn($config);

        return $provider;
    }
}
