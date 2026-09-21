<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service\StructuredData;

use Nowo\SeoKitBundle\Model\SeoSiteConfig;
use Nowo\SeoKitBundle\Model\StructuredData\SoftwareApplicationNode;
use Nowo\SeoKitBundle\Model\StructuredData\WebPageNode;
use Nowo\SeoKitBundle\Service\StructuredData\SiteStructuredDataFactory;
use PHPUnit\Framework\TestCase;

final class SiteStructuredDataFactoryTest extends TestCase
{
    public function testBuildsOrganisationAndWebsiteFromConfig(): void
    {
        $config = new SeoSiteConfig(
            siteName: 'Nowo',
            titleTemplate: '%page% · %site%',
            indexable: true,
            robots: 'index, follow',
            openGraphImage: null,
            twitterSite: null,
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
            googleSiteVerification: null,
            bingSiteVerification: null,
            byLocale: [
                'es' => [
                    'title'                   => 'Inicio',
                    'description'             => 'Desc',
                    'homeTitle'               => 'Home',
                    'organisationDescription' => 'Org ES',
                ],
            ],
        );

        $graph   = (new SiteStructuredDataFactory())->build($config, 'es', 'https://nowo.tech');
        $decoded = $graph->toArray();

        self::assertCount(2, $decoded['@graph']);
        self::assertSame('Organization', $decoded['@graph'][0]['@type']);
        self::assertSame('https://nowo.tech/logo.png', $decoded['@graph'][0]['logo']);
        self::assertSame('Org ES', $decoded['@graph'][0]['description']);
        self::assertSame('WebSite', $decoded['@graph'][1]['@type']);
    }

    public function testUsesTranslatorAndAbsoluteHttpLogo(): void
    {
        $config = new SeoSiteConfig(
            siteName: '',
            titleTemplate: '%page%',
            indexable: true,
            robots: 'index, follow',
            openGraphImage: null,
            twitterSite: null,
            organisationLegalName: null,
            organisationLogo: 'https://cdn.example/logo.png',
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

        $graph = (new SiteStructuredDataFactory())->build(
            $config,
            'en',
            'https://nowo.tech',
            'https://nowo.tech/default.png',
            static fn (string $key): string => $key === 'site.seo.site_name' ? 'Translated' : 'Default desc',
        );

        $org = $graph->toArray()['@graph'][0];
        self::assertSame('Translated', $org['name']);
        self::assertSame('https://cdn.example/logo.png', $org['logo']);
        self::assertSame('Default desc', $org['description']);
    }

    public function testEmptyLogoFallsBackToDefaultAbsolute(): void
    {
        $config = new SeoSiteConfig(
            siteName: 'Nowo',
            titleTemplate: '%page%',
            indexable: true,
            robots: 'index, follow',
            openGraphImage: null,
            twitterSite: null,
            organisationLegalName: null,
            organisationLogo: '',
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

        $org = (new SiteStructuredDataFactory())
            ->build($config, 'en', 'https://nowo.tech', 'https://nowo.tech/default.png')
            ->toArray()['@graph'][0];

        self::assertSame('https://nowo.tech/default.png', $org['logo']);
    }

    public function testWebPageAndSoftwareApplicationNodes(): void
    {
        self::assertSame(
            [
                '@type'       => 'WebPage',
                'name'        => 'About',
                'url'         => 'https://nowo.tech/about',
                'description' => 'About us',
                'inLanguage'  => 'en',
            ],
            (new WebPageNode('About', 'https://nowo.tech/about', 'About us', 'en'))->toArray(),
        );

        $app = (new SoftwareApplicationNode('Engine', 'https://nowo.tech/app', '', ''))->toArray();
        self::assertSame('SoftwareApplication', $app['@type']);
        self::assertSame('BusinessApplication', $app['applicationCategory']);
        self::assertArrayNotHasKey('description', $app);
    }
}
