<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Model;

use Nowo\SeoKitBundle\Model\SeoSiteConfig;
use PHPUnit\Framework\TestCase;

final class SeoSiteConfigTest extends TestCase
{
    public function testComposeTitleAppliesPlaceholders(): void
    {
        $config = $this->config(titleTemplate: '%page% · %site%', siteName: 'Nowo');

        self::assertSame('Pricing · Nowo', $config->composeTitle('Pricing.'));
    }

    public function testComposeTitleIgnoresBrokenTemplateWithoutPagePlaceholder(): void
    {
        $config = $this->config(titleTemplate: 'Always the same', siteName: 'Nowo');

        self::assertSame('Pricing', $config->composeTitle('Pricing'));
    }

    public function testComposeTitleReturnsSiteNameForEmptyPageTitle(): void
    {
        $config = $this->config(titleTemplate: '%page% · %site%', siteName: 'Nowo');

        self::assertSame('Nowo', $config->composeTitle(''));
    }

    public function testLocaleHelpersAndPostalAddress(): void
    {
        $config = new SeoSiteConfig(
            siteName: 'Nowo',
            titleTemplate: '%page%',
            indexable: true,
            robots: 'index, follow',
            openGraphImage: null,
            twitterSite: null,
            organisationLegalName: null,
            organisationLogo: null,
            contactEmail: null,
            contactPhone: null,
            streetAddress: null,
            postalCode: '08001',
            addressLocality: null,
            addressRegion: null,
            addressCountry: null,
            socialProfiles: [],
            googleSiteVerification: null,
            bingSiteVerification: null,
            byLocale: [
                'es' => [
                    'title'                   => 'Título',
                    'description'             => 'Desc',
                    'homeTitle'               => 'Inicio',
                    'organisationDescription' => 'Org',
                ],
            ],
        );

        self::assertSame('Título', $config->titleFor('es'));
        self::assertSame('Desc', $config->descriptionFor('es'));
        self::assertSame('Inicio', $config->homeTitleFor('es'));
        self::assertSame('Org', $config->organisationDescriptionFor('es'));
        self::assertNull($config->titleFor('fr'));
        self::assertTrue($config->hasPostalAddress());
    }

    public function testSocialProfileUrlsAndExplicitNone(): void
    {
        $urls = $this->config('%page%', 'Nowo', ['https://x.com/nowo', 'http://fb.example/n']);
        self::assertSame(['https://x.com/nowo', 'http://fb.example/n'], $urls->socialProfileUrls());
        self::assertFalse($urls->hasNoSocialProfiles());

        $none = $this->config('%page%', 'Nowo', ['None']);
        self::assertSame([], $none->socialProfileUrls(), 'The sentinel never reaches sameAs.');
        self::assertTrue($none->hasNoSocialProfiles());

        self::assertFalse($this->config('%page%', 'Nowo')->hasNoSocialProfiles(), 'Empty is not the same as none.');
    }

    /**
     * @param list<string> $socialProfiles
     */
    private function config(string $titleTemplate, string $siteName, array $socialProfiles = []): SeoSiteConfig
    {
        return new SeoSiteConfig(
            siteName: $siteName,
            titleTemplate: $titleTemplate,
            indexable: true,
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
            socialProfiles: $socialProfiles,
            googleSiteVerification: null,
            bingSiteVerification: null,
            byLocale: [],
        );
    }
}
