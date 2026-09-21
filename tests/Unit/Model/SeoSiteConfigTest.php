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

    private function config(string $titleTemplate, string $siteName): SeoSiteConfig
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
            socialProfiles: [],
            googleSiteVerification: null,
            bingSiteVerification: null,
            byLocale: [],
        );
    }
}
