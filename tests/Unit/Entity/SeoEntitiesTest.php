<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Entity;

use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Entity\SeoSiteSettingsTranslation;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use PHPUnit\Framework\TestCase;

final class SeoEntitiesTest extends TestCase
{
    public function testSeoSurfaceAccessorsAndCleaning(): void
    {
        $surface = new SeoSurface();

        self::assertNull($surface->getId());

        $surface
            ->setSurfaceKey('  page:home  ')
            ->setLocale('  ES  ')
            ->setMetaTitle('  Title  ')
            ->setMetaDescription('  Desc  ')
            ->setMetaRobots('  index  ')
            ->setCanonicalOverride('  /x  ')
            ->setOpenGraphImage('  /og.png  ')
            ->setStructuredDataExtra(['@type' => 'WebPage']);

        self::assertSame('page:home', $surface->getSurfaceKey());
        self::assertSame('es', $surface->getLocale());
        self::assertSame('Title', $surface->getMetaTitle());
        self::assertSame('Desc', $surface->getMetaDescription());
        self::assertSame('index', $surface->getMetaRobots());
        self::assertSame('/x', $surface->getCanonicalOverride());
        self::assertSame('/og.png', $surface->getOpenGraphImage());
        self::assertSame(['@type' => 'WebPage'], $surface->getStructuredDataExtra());

        $surface
            ->setMetaTitle('   ')
            ->setMetaDescription(null)
            ->setStructuredDataExtra([]);

        self::assertNull($surface->getMetaTitle());
        self::assertNull($surface->getMetaDescription());
        self::assertNull($surface->getStructuredDataExtra());
    }

    public function testSeoSiteSettingsTranslationAccessors(): void
    {
        $settings    = new SeoSiteSettings();
        $translation = new SeoSiteSettingsTranslation();

        self::assertNull($translation->getId());
        self::assertNull($translation->getSettings());

        $translation
            ->setSettings($settings)
            ->setLocale('  FR  ')
            ->setDefaultTitle('Title')
            ->setDefaultDescription('Desc')
            ->setHomeTitle('Home')
            ->setOrganisationDescription('Org');

        self::assertSame($settings, $translation->getSettings());
        self::assertSame('fr', $translation->getLocale());
        self::assertSame('Title', $translation->getDefaultTitle());
        self::assertSame('Desc', $translation->getDefaultDescription());
        self::assertSame('Home', $translation->getHomeTitle());
        self::assertSame('Org', $translation->getOrganisationDescription());

        $translation
            ->setDefaultTitle(null)
            ->setDefaultDescription(null)
            ->setHomeTitle(null)
            ->setOrganisationDescription(null);

        self::assertNull($translation->getDefaultTitle());
        self::assertNull($translation->getOrganisationDescription());
    }

    public function testSeoSiteSettingsFullLifecycle(): void
    {
        $settings = new SeoSiteSettings();

        self::assertSame(SeoSiteSettings::SINGLETON_ID, $settings->getId());
        self::assertNull($settings->getUpdatedAt());

        $settings
            ->setSiteName('Nowo')
            ->setTitleTemplate('%page% · %site%')
            ->setIndexable(false)
            ->setDefaultRobots('index, follow')
            ->setDefaultOpenGraphImage('  /og.png  ')
            ->setTwitterSite('  nowo  ')
            ->setOrganisationLegalName('  Nowo SL  ')
            ->setOrganisationLogo('  /logo.png  ')
            ->setContactEmail('  hello@nowo.tech  ')
            ->setContactPhone('  +34  ')
            ->setStreetAddress('  Street  ')
            ->setPostalCode('  08001  ')
            ->setAddressLocality('  Barcelona  ')
            ->setAddressRegion('  CAT  ')
            ->setAddressCountry('  ES  ')
            ->setSocialProfiles([' https://x.com/nowo ', '', 'https://linkedin.com/company/nowo'])
            ->setGoogleSiteVerification('  google  ')
            ->setBingSiteVerification('  bing  ')
            ->touchUpdatedAt();

        self::assertSame('Nowo', $settings->getSiteName());
        self::assertSame('%page% · %site%', $settings->getTitleTemplate());
        self::assertFalse($settings->isIndexable());
        self::assertSame('noindex, nofollow', $settings->effectiveRobots());
        self::assertSame('index, follow', $settings->getDefaultRobots());
        self::assertSame('/og.png', $settings->getDefaultOpenGraphImage());
        self::assertSame('@nowo', $settings->getTwitterSite());
        self::assertSame('Nowo SL', $settings->getOrganisationLegalName());
        self::assertSame('/logo.png', $settings->getOrganisationLogo());
        self::assertSame('hello@nowo.tech', $settings->getContactEmail());
        self::assertSame('+34', $settings->getContactPhone());
        self::assertSame('Street', $settings->getStreetAddress());
        self::assertSame('08001', $settings->getPostalCode());
        self::assertSame('Barcelona', $settings->getAddressLocality());
        self::assertSame('CAT', $settings->getAddressRegion());
        self::assertSame('ES', $settings->getAddressCountry());
        self::assertTrue($settings->hasPostalAddress());
        self::assertSame(['https://x.com/nowo', 'https://linkedin.com/company/nowo'], $settings->getSocialProfiles());
        self::assertSame('google', $settings->getGoogleSiteVerification());
        self::assertSame('bing', $settings->getBingSiteVerification());
        self::assertNotNull($settings->getUpdatedAt());

        $settings->setIndexable(true);
        self::assertSame('index, follow', $settings->effectiveRobots());

        $settings
            ->setDefaultOpenGraphImage('   ')
            ->setTwitterSite(null)
            ->setContactEmail('')
            ->setSocialProfiles([]);

        self::assertNull($settings->getDefaultOpenGraphImage());
        self::assertNull($settings->getTwitterSite());
        self::assertNull($settings->getContactEmail());
        self::assertSame([], $settings->getSocialProfiles());
    }

    public function testSeoSiteSettingsTranslationsCollection(): void
    {
        $settings = new SeoSiteSettings();

        self::assertNull($settings->findTranslation('es'));

        $created = $settings->translationFor('ES');
        self::assertSame('es', $created->getLocale());
        self::assertSame($settings, $created->getSettings());
        self::assertSame($created, $settings->findTranslation('es'));
        self::assertSame($created, $settings->translationFor('es'));
        self::assertCount(1, $settings->getTranslations());

        $other = new SeoSiteSettingsTranslation();
        $other->setLocale('fr');
        $settings->addTranslation($other);
        $settings->addTranslation($other);
        self::assertCount(2, $settings->getTranslations());

        $settings->removeTranslation($other);
        self::assertCount(1, $settings->getTranslations());
        self::assertNull($settings->findTranslation('fr'));
    }
}
