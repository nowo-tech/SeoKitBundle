<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Ui;

use Nowo\SeoKitBundle\Service\Audit\SeoAuditRow;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\BackLink;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function count;
use function strtoupper;

/**
 * Page plumbing of the UiKit page contract (REQ-UI-003) for the SeoKit admin: resolves the layout the scaffolds
 * extend (`web_ui.layout_template`, null = `nowo_ui_kit.layouts.admin`), the per-page templates
 * (`web_ui.templates.<page>`) and the form themes (`web_ui.form_themes`), and builds the view models of the settings,
 * surfaces list and surface form pages. Configuration only: no request state, safe in worker mode.
 */
final readonly class SeoKitPages
{
    /** Bundle name of the view models (theme exceptions `nowo_ui_kit.theme.bundles.NowoSeoKitBundle`). */
    public const BUNDLE = 'NowoSeoKitBundle';

    /** Translation domain of the labels. */
    public const DOMAIN = 'NowoSeoKitBundle';

    /** Default page templates (`web_ui.templates.<page>`). */
    public const TEMPLATES = [
        'settings'       => '@NowoSeoKitBundle/admin/settings.html.twig',
        'surfaces_index' => '@NowoSeoKitBundle/admin/surfaces_index.html.twig',
        'surfaces_form'  => '@NowoSeoKitBundle/admin/surfaces_form.html.twig',
    ];

    /** Default form theme of the admin forms (`web_ui.form_themes`). */
    public const DEFAULT_FORM_THEME = '@NowoUiKit/form/nowo_ui_layout.html.twig';

    /**
     * @param string|null $layoutTemplate `web_ui.layout_template` (null = UiKit admin layout)
     * @param array<string, string> $templates `web_ui.templates`
     * @param list<string> $formThemes `web_ui.form_themes`
     * @param SeoKitAreaNav|null $areaNav admin navigation rendered as page-header tabs
     */
    public function __construct(
        private ?string $layoutTemplate = null,
        private array $templates = [],
        private array $formThemes = [self::DEFAULT_FORM_THEME],
        private ?SeoKitAreaNav $areaNav = null,
    ) {
    }

    /**
     * Translation key of the bundle domain as a UiKit label.
     *
     * @param array<string, float|int|string|null> $params
     */
    public static function label(string $key, array $params = []): Label
    {
        return new Label($key, self::DOMAIN, $params);
    }

    /**
     * Template of a page: the `web_ui.templates.<page>` override, else the bundle template.
     */
    public function template(string $page): string
    {
        $override = $this->templates[$page] ?? '';

        return $override !== '' ? $override : (self::TEMPLATES[$page] ?? $page);
    }

    /**
     * @return list<string>
     */
    public function formThemes(): array
    {
        return $this->formThemes;
    }

    /**
     * Layout the scaffolds extend (`page.layout`): `web_ui.layout_template`, null = UiKit `nowo_ui_layout.admin`.
     */
    public function layout(): ?string
    {
        return $this->layoutTemplate === '' ? null : $this->layoutTemplate;
    }

    /**
     * Site settings page (UiKit form scaffold; the template fills `nowo_ui_form_body` with the locale tabs).
     */
    public function settingsPage(FormView $form, UrlGeneratorInterface $urls): FormPage
    {
        $self = $urls->generate('nowo_seo_kit_admin_settings');

        return new FormPage(
            new PageHeader(
                self::label('nowo_seo_kit.admin.settings.title'),
                self::label('nowo_seo_kit.admin.settings.intro'),
                tabs: $this->tabs($self),
            ),
            $form,
            submitLabel: self::label('nowo_seo_kit.admin.save'),
            bundle: self::BUNDLE,
            layout: $this->layout(),
            attributes: ['data-testid' => 'seo-kit-admin-settings'],
            formThemes: $this->formThemes,
        );
    }

    /**
     * Surfaces of one locale with their resolved head and audit problems (UiKit list scaffold).
     *
     * @param list<SeoAuditRow> $rows audit rows of the locale
     * @param FormView $filters GET locale switcher (SeoSurfaceLocaleFilterType)
     */
    public function surfacesPage(array $rows, string $locale, FormView $filters, UrlGeneratorInterface $urls): ListPage
    {
        $index   = $urls->generate('nowo_seo_kit_admin_surfaces');
        $data    = [];
        $actions = [];
        foreach ($rows as $i => $row) {
            $data[$i] = [
                'id'           => $i,
                'surface'      => $row->surfaceKey,
                'path'         => $row->path,
                'title'        => $row->title,
                'problems'     => $row->problems(),
                'has_override' => $row->hasOverride,
            ];
            $actions[$i] = [
                new Action(
                    'edit',
                    self::label('nowo_seo_kit.admin.edit'),
                    $urls->generate('nowo_seo_kit_admin_surfaces_edit', ['locale' => $locale, 'surfaceKey' => $row->surfaceKey]),
                    attributes: ['data-testid' => 'seo-kit-surface-edit-' . $i],
                ),
            ];
        }

        return new ListPage(
            new PageHeader(
                self::label('nowo_seo_kit.admin.surfaces.title'),
                self::label('nowo_seo_kit.admin.surfaces.intro'),
                tabs: $this->tabs($index),
            ),
            [
                new Column('surface', self::label('nowo_seo_kit.admin.surfaces.column.surface'), required: true),
                new Column('path', self::label('nowo_seo_kit.admin.surfaces.column.path'), priority: 2),
                new Column('title', self::label('nowo_seo_kit.admin.surfaces.column.title')),
                new Column('problems', self::label('nowo_seo_kit.admin.surfaces.column.problems')),
            ],
            $data,
            filters: $filters,
            rowActions: static fn (array $row): array => $actions[$row['id']] ?? [],
            empty: new EmptyState(self::label('nowo_seo_kit.admin.surfaces.empty_title'), self::label('nowo_seo_kit.admin.surfaces.empty')),
            caption: self::label('nowo_seo_kit.admin.surfaces.caption', ['%locale%' => strtoupper($locale)]),
            bundle: self::BUNDLE,
            layout: $this->layout(),
            attributes: ['data-testid' => 'seo-kit-admin-surfaces'],
            actionsLabel: self::label('nowo_seo_kit.admin.surfaces.column.actions'),
        );
    }

    /**
     * Override form of one surface in one locale (UiKit form scaffold).
     */
    public function surfaceFormPage(string $surfaceKey, string $locale, FormView $form, UrlGeneratorInterface $urls): FormPage
    {
        $list = $urls->generate('nowo_seo_kit_admin_surfaces', ['locale' => $locale]);

        return new FormPage(
            new PageHeader(
                self::label('nowo_seo_kit.admin.surfaces.edit_title', ['%surface%' => $surfaceKey]),
                self::label('nowo_seo_kit.admin.surfaces.edit_intro', ['%locale%' => strtoupper($locale)]),
                back: new BackLink($list, self::label('nowo_seo_kit.admin.back')),
            ),
            $form,
            submitLabel: self::label('nowo_seo_kit.admin.save'),
            cancelHref: $list,
            cancelLabel: self::label('nowo_seo_kit.admin.back'),
            bundle: self::BUNDLE,
            layout: $this->layout(),
            attributes: ['data-testid' => 'seo-kit-admin-surface-form'],
            formThemes: $this->formThemes,
        );
    }

    /**
     * Admin navigation as page-header tabs, only when it links more than one page.
     */
    private function tabs(string $current): ?AreaNav
    {
        $nav = $this->areaNav?->areaNav(SeoKitAreaNav::AREA, ['current' => $current]);

        return $nav instanceof AreaNav && count($nav->items) > 1 ? $nav : null;
    }
}
