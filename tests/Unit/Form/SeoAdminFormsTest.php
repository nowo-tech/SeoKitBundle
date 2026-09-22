<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Form;

use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Form\SeoSiteSettingsType;
use Nowo\SeoKitBundle\Form\SeoSurfaceType;
use Nowo\SeoKitBundle\Service\OriginUrlGuard;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

final class SeoAdminFormsTest extends TestCase
{
    public function testSiteSettingsFormRoundTripsLocalesAndSocialProfiles(): void
    {
        $settings = new SeoSiteSettings();
        $settings->setSiteName('Nowo');
        $settings->setTitleTemplate('%page% · %site%');
        $settings->setSocialProfiles(['https://x.com/nowo']);
        $settings->translationFor('en')->setDefaultTitle('Home');

        $form = $this->factory()->create(SeoSiteSettingsType::class, $settings, [
            'enabled_locales' => ['en', 'es'],
            'default_locale'  => 'en',
        ]);

        self::assertSame('nowo_seo_site', $form->getConfig()->getType()->getBlockPrefix());
        self::assertSame('https://x.com/nowo', $form->get('socialProfilesText')->getData());
        self::assertSame('Home', $form->get('defaultTitle_en')->getData());
        self::assertSame(['en', 'es'], $form->createView()->vars['enabled_locales']);

        $form->submit([
            'siteName'                   => 'Nowo Tech',
            'titleTemplate'              => '%page% · %site%',
            'indexable'                  => '1',
            'defaultRobots'              => 'index, follow',
            'defaultOpenGraphImage'      => '',
            'twitterSite'                => '',
            'organisationLegalName'      => '',
            'organisationLogo'           => '',
            'contactEmail'               => '',
            'contactPhone'               => '',
            'streetAddress'              => '',
            'postalCode'                 => '',
            'addressLocality'            => '',
            'addressRegion'              => '',
            'addressCountry'             => '',
            'socialProfilesText'         => "https://x.com/nowo\nhttps://linkedin.com/company/nowo",
            'googleSiteVerification'     => '',
            'bingSiteVerification'       => '',
            'defaultTitle_en'            => 'Home EN',
            'homeTitle_en'               => 'Welcome',
            'defaultDescription_en'      => 'Desc EN',
            'organisationDescription_en' => 'Org EN',
            'defaultTitle_es'            => 'Inicio',
            'homeTitle_es'               => '',
            'defaultDescription_es'      => '',
            'organisationDescription_es' => '',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        self::assertSame('Nowo Tech', $settings->getSiteName());
        self::assertSame(['https://x.com/nowo', 'https://linkedin.com/company/nowo'], $settings->getSocialProfiles());
        self::assertSame('Home EN', $settings->findTranslation('en')?->getDefaultTitle());
        self::assertSame('Inicio', $settings->findTranslation('es')?->getDefaultTitle());
    }

    public function testSiteSettingsRejectsBrokenTitleTemplateAndSocialUrls(): void
    {
        $settings = new SeoSiteSettings();
        $form     = $this->factory()->create(SeoSiteSettingsType::class, $settings, [
            'enabled_locales' => ['en'],
            'default_locale'  => 'en',
        ]);

        $form->submit([
            'siteName'                   => 'Nowo',
            'titleTemplate'              => 'broken',
            'indexable'                  => '1',
            'defaultRobots'              => 'index, follow',
            'socialProfilesText'         => "https://ok.example\n\nnot-a-url",
            'defaultTitle_en'            => '',
            'homeTitle_en'               => '',
            'defaultDescription_en'      => '',
            'organisationDescription_en' => '',
        ]);

        self::assertFalse($form->isValid());
        self::assertTrue($form->get('titleTemplate')->getErrors()->count() > 0);
        self::assertTrue($form->get('socialProfilesText')->getErrors()->count() > 0);
    }

    public function testSiteSettingsListenersIgnoreNonEntityData(): void
    {
        $type = new SeoSiteSettingsType();
        $form = $this->factory()->createBuilder()
            ->add('dummy', TextType::class)
            ->getForm();

        $event = new FormEvent($form, null);
        $fill  = new ReflectionMethod(SeoSiteSettingsType::class, 'fillFromEntity');
        $write = new ReflectionMethod(SeoSiteSettingsType::class, 'writeBackToEntity');
        $fill->invoke($type, $event);
        $write->invoke($type, $event);

        self::assertNull($event->getData());
    }

    public function testSurfaceFormValidatesCanonicalAndOpenGraph(): void
    {
        $surface = new SeoSurface();
        $surface->setSurfaceKey('page:home')->setLocale('en');

        $form = $this->factory()->create(SeoSurfaceType::class, $surface);
        self::assertSame('nowo_seo_surface', $form->getConfig()->getType()->getBlockPrefix());

        $form->submit([
            'metaTitle'         => 'Home',
            'metaDescription'   => 'Desc',
            'metaRobots'        => 'index, follow',
            'canonicalOverride' => 'https://evil.example/',
            'openGraphImage'    => 'http://cdn.example/og.png',
        ]);

        self::assertFalse($form->isValid());
        self::assertTrue($form->get('canonicalOverride')->getErrors()->count() > 0);
        self::assertTrue($form->get('openGraphImage')->getErrors()->count() > 0);

        $ok = $this->factory()->create(SeoSurfaceType::class, $surface);
        $ok->submit([
            'metaTitle'         => 'Home',
            'metaDescription'   => 'Desc',
            'metaRobots'        => '',
            'canonicalOverride' => '/es/home',
            'openGraphImage'    => 'https://cdn.example/og.png',
        ]);
        self::assertTrue($ok->isValid(), (string) $ok->getErrors(true, false));
        self::assertSame('/es/home', $surface->getCanonicalOverride());
    }

    private function factory(): FormFactoryInterface
    {
        $validator = Validation::createValidator();

        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension($validator))
            ->addType(new SeoSiteSettingsType())
            ->addType(new SeoSurfaceType(new OriginUrlGuard('https://nowo.tech')))
            ->getFormFactory();
    }
}
