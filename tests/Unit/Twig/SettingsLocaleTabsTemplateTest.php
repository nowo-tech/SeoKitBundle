<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Twig;

use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Form\SeoSiteSettingsType;
use Nowo\SeoKitBundle\Tests\Support\FormKitTestSupport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

/**
 * The per-locale wording of the settings form keeps the translations of locales that are not enabled. The page markup
 * (one tab per locale) is covered by tests/Integration/Contract/PageContractTest.
 */
final class SettingsLocaleTabsTemplateTest extends TestCase
{
    public function testTranslationsOfLocalesThatAreNotEnabledAreKeptOnSave(): void
    {
        $settings = new SeoSiteSettings();
        $settings->translationFor('de')->setDefaultTitle('Startseite');
        $form = $this->form($settings);

        $form->submit($this->payload(['defaultTitle_es' => 'Inicio']));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        self::assertSame('Startseite', $settings->findTranslation('de')?->getDefaultTitle());
        self::assertSame('Inicio', $settings->findTranslation('es')?->getDefaultTitle());
    }

    /**
     * @return FormInterface<SeoSiteSettings>
     */
    private function form(?SeoSiteSettings $settings = null): FormInterface
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(FormKitTestSupport::siteSettingsType())
            ->getFormFactory();

        return $factory->create(SeoSiteSettingsType::class, $settings ?? new SeoSiteSettings(), [
            'enabled_locales' => ['en', 'es', 'fr'],
            'default_locale'  => 'es',
        ]);
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        $data = [
            'siteName'      => 'Nowo',
            'titleTemplate' => '%title% · ' . SeoSiteSettings::TITLE_PLACEHOLDER,
            'defaultRobots' => 'index, follow',
        ];
        foreach (['en', 'es', 'fr'] as $locale) {
            foreach (['defaultTitle', 'homeTitle', 'defaultDescription', 'organisationDescription'] as $field) {
                $data[$field . '_' . $locale] = '';
            }
        }

        return $overrides + $data;
    }
}
