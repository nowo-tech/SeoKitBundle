<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Contract;

use Nowo\FormKitBundle\Twig\LocaleTabsTwigExtension;
use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Tests\Support\AdminPageFixtures;
use Nowo\SeoKitBundle\Ui\SeoKitPages;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Override;
use Twig\Environment;

use function dirname;
use function str_repeat;
use function strpos;
use function substr_count;

/**
 * REQ-UI-003: every admin page extends a UiKit scaffold, fills the canonical blocks, has no <html>, CDN asset,
 * inline handler or confirm(), prints framework classes only through theme slots, reflects the stub theme and has no
 * raw or missing translation key (UiKit PageContractTestCase, strict missing-message rule); plus the page-specific
 * markup (settings locale tabs, surfaces rows / problems / filters, surface form) rendered in the same environment.
 */
final class PageContractTest extends PageContractTestCase
{
    private const WORDING_TABS = 'nowo-seo-kit-settings-wording';

    public static function pageFixtures(): iterable
    {
        yield 'settings' => new PageFixture(SeoKitPages::TEMPLATES['settings'], AdminPageFixtures::settings());
        yield 'settings (noindex)' => new PageFixture(SeoKitPages::TEMPLATES['settings'], AdminPageFixtures::settings(false));
        yield 'surfaces index' => new PageFixture(SeoKitPages::TEMPLATES['surfaces_index'], AdminPageFixtures::surfacesIndex());
        yield 'surfaces index (empty, site noindex)' => new PageFixture(SeoKitPages::TEMPLATES['surfaces_index'], AdminPageFixtures::surfacesIndex([], false));
        yield 'surface form' => new PageFixture(SeoKitPages::TEMPLATES['surfaces_form'], AdminPageFixtures::surfaceForm());
    }

    public function testSettingsWordingRendersOneUiKitTabPerLocaleWithTheDefaultLocaleFirst(): void
    {
        $id   = self::WORDING_TABS;
        $html = $this->renderPage(new PageFixture(SeoKitPages::TEMPLATES['settings'], AdminPageFixtures::settings()))->html;

        self::assertStringContainsString('data-testid="' . $id . '"', $html);
        self::assertStringContainsString('data-nowo-ui-locale-tabs', $html);
        self::assertSame(3, substr_count($html, 'role="tab"'));
        foreach (AdminPageFixtures::LOCALES as $locale) {
            self::assertStringContainsString('id="tab-' . $id . '-' . $locale . '"', $html);
            self::assertStringContainsString('id="tab-' . $id . '-' . $locale . '-panel"', $html);
            foreach (['defaultTitle', 'homeTitle', 'defaultDescription', 'organisationDescription'] as $field) {
                self::assertSame(1, substr_count($html, 'name="nowo_seo_site[' . $field . '_' . $locale . ']"'), $field . '_' . $locale);
            }
            self::assertStringContainsString('Default title (' . $locale . ')', $html);
        }
        // default locale (en) first and open, marked as the default language
        self::assertLessThan(strpos($html, 'id="tab-' . $id . '-es"'), strpos($html, 'id="tab-' . $id . '-en"'));
        self::assertMatchesRegularExpression('/id="tab-' . $id . '-en"[^>]*aria-selected="true"[^>]*data-default/s', $html);
        // the localised fields are inside their own panel, the wording sits before the organisation fields
        $esPanel = strpos($html, 'id="tab-' . $id . '-es-panel"');
        $frPanel = strpos($html, 'id="tab-' . $id . '-fr-panel"');
        self::assertGreaterThan($esPanel, strpos($html, 'name="nowo_seo_site[defaultTitle_es]"'));
        self::assertLessThan($frPanel, strpos($html, 'name="nowo_seo_site[defaultTitle_es]"'));
        self::assertLessThan(strpos($html, 'name="nowo_seo_site[organisationLegalName]"'), strpos($html, 'data-testid="seo-kit-settings-wording"'));
        self::assertSame(1, substr_count($html, 'name="nowo_seo_site[siteName]"'));
        self::assertSame(1, substr_count($html, 'name="nowo_seo_site[_token]"'));
        // admin navigation as header tabs, settings active
        self::assertStringContainsString('data-testid="seo-kit-nav-settings"', $html);
        self::assertStringContainsString('data-testid="seo-kit-nav-surfaces"', $html);
        self::assertStringNotContainsString('data-testid="seo-kit-settings-noindex"', $html);
    }

    public function testSettingsInvalidLocaleTabIsMarkedAndOpenedAndNoindexIsAnnounced(): void
    {
        $payload = ['siteName' => 'Nowo', 'titleTemplate' => '%title% · ' . SeoSiteSettings::TITLE_PLACEHOLDER, 'defaultRobots' => 'index, follow'];
        foreach (AdminPageFixtures::LOCALES as $locale) {
            foreach (['defaultTitle', 'homeTitle', 'defaultDescription', 'organisationDescription'] as $field) {
                $payload[$field . '_' . $locale] = '';
            }
        }
        $payload['defaultTitle_fr'] = str_repeat('x', 300);

        $id   = self::WORDING_TABS;
        $html = $this->renderPage(new PageFixture(SeoKitPages::TEMPLATES['settings'], AdminPageFixtures::settings(false, 'en', $payload)))->html;

        self::assertStringContainsString('data-nowo-ui-tabs-active="' . $id . '-fr"', $html);
        self::assertMatchesRegularExpression('/id="tab-' . $id . '-fr"[^>]*aria-selected="true"[^>]*data-invalid/s', $html);
        self::assertMatchesRegularExpression('/id="tab-' . $id . '-en"[^>]*aria-selected="false"/s', $html);
        self::assertMatchesRegularExpression('/<input[^>]*name="nowo_seo_site\[defaultTitle_fr\]"[^>]*aria-invalid="true"/s', $html);
        self::assertStringContainsString('data-testid="seo-kit-settings-noindex"', $html);
    }

    public function testSurfacesListRendersRowsProblemsFiltersAndEditActions(): void
    {
        $html = $this->renderPage(new PageFixture(SeoKitPages::TEMPLATES['surfaces_index'], AdminPageFixtures::surfacesIndex()))->html;

        self::assertStringContainsString('page:home', $html);
        self::assertStringContainsString('/blog/symfony-seo', $html);
        self::assertStringContainsString('data-testid="seo-kit-problem-published_but_noindex"', $html);
        self::assertStringContainsString('Published but noindex', $html);
        self::assertStringContainsString('Missing title', $html);
        self::assertStringContainsString('href="/admin/seo/surfaces/en/edit/blog:post:42"', $html);
        self::assertStringContainsString('data-testid="seo-kit-surface-edit-0"', $html);
        self::assertStringContainsString('name="locale"', $html);
        self::assertMatchesRegularExpression('/<option value="es"[^>]*>ES<\/option>/', $html);
        self::assertStringNotContainsString('data-testid="seo-kit-surfaces-site-noindex"', $html);

        $empty = $this->renderPage(new PageFixture(SeoKitPages::TEMPLATES['surfaces_index'], AdminPageFixtures::surfacesIndex([], false)))->html;
        self::assertStringContainsString('data-testid="seo-kit-surfaces-site-noindex"', $empty);
        self::assertStringContainsString('No surfaces', $empty);
    }

    public function testSurfaceFormRendersTheFormWithBackLinkAndCancel(): void
    {
        $html = $this->renderPage(new PageFixture(SeoKitPages::TEMPLATES['surfaces_form'], AdminPageFixtures::surfaceForm()))->html;

        self::assertStringContainsString('Edit SEO — page:home', $html);
        self::assertStringContainsString('name="nowo_seo_surface[metaTitle]"', $html);
        self::assertStringContainsString('value="Home | Nowo"', $html);
        self::assertStringContainsString('name="nowo_seo_surface[_token]"', $html);
        self::assertStringContainsString('href="/admin/seo/surfaces?locale=en"', $html);
    }

    #[Override]
    protected static function templatePaths(): array
    {
        $root = dirname(__DIR__, 3);

        return [
            'NowoSeoKitBundle'  => $root . '/src/Resources/views',
            'NowoFormKitBundle' => $root . '/vendor/nowo-tech/form-kit-bundle/src/Resources/views',
        ];
    }

    #[Override]
    protected static function translationDirs(): array
    {
        return [dirname(__DIR__, 3) . '/src/Resources/translations'];
    }

    #[Override]
    protected static function templatesDocument(): string
    {
        return dirname(__DIR__, 3) . '/docs/TEMPLATES.md';
    }

    #[Override]
    protected static function formThemes(): array
    {
        return [SeoKitPages::DEFAULT_FORM_THEME];
    }

    #[Override]
    protected static function strictMissingTranslations(): bool
    {
        return true;
    }

    #[Override]
    protected function configureTwig(Environment $twig): void
    {
        $twig->addExtension(new LocaleTabsTwigExtension(AdminPageFixtures::LOCALES, 'en'));
    }
}
