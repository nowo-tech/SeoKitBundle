<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Twig;

use Nowo\FormKitBundle\Twig\LocaleTabsTwigExtension;
use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Form\SeoSiteSettingsType;
use Nowo\SeoKitBundle\Tests\Support\FormKitTestSupport;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

use function dirname;
use function str_repeat;
use function strpos;
use function substr_count;

/**
 * The per-locale wording of /settings/seo renders as one tab per enabled locale (FormKit `fields` mode).
 */
final class SettingsLocaleTabsTemplateTest extends TestCase
{
    private const ID = 'nowo-seo-kit-settings-wording';

    public function testWordingRendersOneUiKitTabPerLocaleWithTheDefaultLocaleFirst(): void
    {
        $html = $this->render($this->form(), uiKit: true);

        self::assertStringContainsString('data-testid="' . self::ID . '"', $html);
        self::assertStringContainsString('data-nowo-ui-locale-tabs', $html);
        self::assertSame(3, substr_count($html, 'role="tab"'));
        foreach (['en', 'es', 'fr'] as $locale) {
            self::assertStringContainsString('id="tab-' . self::ID . '-' . $locale . '"', $html);
            self::assertStringContainsString('id="tab-' . self::ID . '-' . $locale . '-panel"', $html);
            foreach (['defaultTitle', 'homeTitle', 'defaultDescription', 'organisationDescription'] as $field) {
                self::assertSame(1, substr_count($html, 'name="nowo_seo_site[' . $field . '_' . $locale . ']"'), $field . '_' . $locale);
            }
        }
        // default locale (es) first and open, marked as the default language
        self::assertLessThan(strpos($html, 'id="tab-' . self::ID . '-en"'), strpos($html, 'id="tab-' . self::ID . '-es"'));
        self::assertStringContainsString('data-nowo-ui-tabs-active="' . self::ID . '-es"', $html);
        self::assertMatchesRegularExpression('/id="tab-' . self::ID . '-es"[^>]*aria-selected="true"[^>]*data-default/s', $html);
        // the localised fields are inside their own panel
        $esPanel = strpos($html, 'id="tab-' . self::ID . '-es-panel"');
        $enPanel = strpos($html, 'id="tab-' . self::ID . '-en-panel"');
        self::assertGreaterThan($esPanel, strpos($html, 'name="nowo_seo_site[defaultTitle_es]"'));
        self::assertLessThan($enPanel, strpos($html, 'name="nowo_seo_site[defaultTitle_es]"'));
        // UiKit runtime + styles on the minimal layout, rendered once, no inline handlers
        self::assertStringContainsString('/bundles/nowouikit/js/nowo-ui-tabs.js', $html);
        self::assertStringContainsString('/bundles/nowouikit/css/nowo-ui.css', $html);
        self::assertSame(1, substr_count($html, 'name="nowo_seo_site[siteName]"'));
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+=/i', $html);
    }

    public function testInvalidLocaleTabIsMarkedAndOpened(): void
    {
        $form = $this->form();
        $form->submit($this->payload(['defaultTitle_fr' => str_repeat('x', 300)]));
        self::assertFalse($form->isValid());

        $html = $this->render($form, uiKit: true);

        self::assertStringContainsString('data-nowo-ui-tabs-active="' . self::ID . '-fr"', $html);
        self::assertMatchesRegularExpression('/id="tab-' . self::ID . '-fr"[^>]*aria-selected="true"[^>]*data-invalid/s', $html);
        self::assertMatchesRegularExpression('/id="tab-' . self::ID . '-es"[^>]*aria-selected="false"/s', $html);
        self::assertMatchesRegularExpression('/<input[^>]*name="nowo_seo_site\[defaultTitle_fr\]"[^>]*aria-invalid="true"/s', $html);
    }

    public function testNeutralFormKitMarkupWithoutUiKit(): void
    {
        $html = $this->render($this->form(), uiKit: false);

        self::assertStringContainsString('class="nowo-form-kit-locale-tabs"', $html);
        self::assertStringContainsString('id="tab-' . self::ID . '-es"', $html);
        self::assertStringNotContainsString('data-nowo-ui-locale-tabs', $html);
        self::assertStringNotContainsString('nowo-ui-tabs.js', $html);
        self::assertStringNotContainsString('<script', $html);
    }

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

    /**
     * @param FormInterface<SeoSiteSettings> $form
     */
    private function render(FormInterface $form, bool $uiKit): string
    {
        $root = dirname(__DIR__, 3);
        $fs   = new FilesystemLoader([$root . '/vendor/symfony/twig-bridge/Resources/views/Form']);
        $fs->addPath($root . '/src/Resources/views', 'NowoSeoKitBundle');
        $fs->addPath($root . '/vendor/nowo-tech/form-kit-bundle/src/Resources/views', 'NowoFormKitBundle');
        if ($uiKit) {
            $fs->addPath($root . '/vendor/nowo-tech/ui-kit-bundle/src/Resources/views', 'NowoUiKitBundle');
            $fs->addPath($root . '/vendor/nowo-tech/ui-kit-bundle/src/Resources/views', 'NowoUiKit');
        }

        $twig = new Environment($fs, ['strict_variables' => true, 'cache' => false]);
        $twig->addExtension(new TranslationExtension());
        $twig->addExtension(new FormExtension());
        $twig->addExtension(new LocaleTabsTwigExtension(['en', 'es', 'fr'], 'es'));
        $twig->addExtension(new AssetExtension(new Packages(null, ['nowo_ui_kit' => new PathPackage('/bundles/nowouikit', new EmptyVersionStrategy())])));
        if ($uiKit) {
            $twig->addExtension(new ThemeExtension(new Theme()));
        }

        $engine = new TwigRendererEngine(['form_div_layout.html.twig'], $twig);
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => static fn (): FormRenderer => new FormRenderer($engine),
        ]));

        return $twig->render('@NowoSeoKitBundle/admin/settings.html.twig', [
            'form'            => $form->createView(),
            'settings'        => $form->getData(),
            'enabled_locales' => ['en', 'es', 'fr'],
            'default_locale'  => 'es',
        ]);
    }
}
