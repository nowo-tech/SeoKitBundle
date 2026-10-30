# Templates

Pages of SeoKitBundle on the UiKit page contract (REQ-UI-003, UiKit ^2.17). The admin controllers build the UiKit view
models (`Nowo\UiKitBundle\View\*`) through `Nowo\SeoKitBundle\Ui\SeoKitPages`, which also resolves the layout
(`web_ui.layout_template`, null = `nowo_ui_kit.layouts.admin`), the per-page templates (`web_ui.templates.<page>`) and
the form themes (`web_ui.form_themes`). Templates extend a UiKit scaffold and fill data blocks only. Contract test:
`tests/Integration/Contract/PageContractTest.php` (UiKit `PageContractTestCase`, strict missing-message rule);
functional test on the real UiKit layout: `tests/Integration/SeoKitAdminKernelTest.php`.

## Table of contents

- [Pages](#pages)
- [Shared page context](#shared-page-context)
- [Locale tabs](#locale-tabs)
- [Unique pages](#unique-pages)
- [Per-page overrides](#per-page-overrides)
- [Bundle partials](#bundle-partials)
- [Navigation](#navigation)
- [Allowed exceptions](#allowed-exceptions)
- [Head template](#head-template)

## Pages

Every view model passes `bundle: 'NowoSeoKitBundle'` (theme exception `nowo_ui_kit.theme.bundles.NowoSeoKitBundle`)
and `layout: SeoKitPages::layout()`. Labels are `NowoSeoKitBundle` translation keys (`SeoKitPages::label()`).

| Page (`web_ui.templates` id) | Template | Scaffold | View model | Data blocks filled |
| --- | --- | --- | --- | --- |
| Site settings (`settings`) | `admin/settings.html.twig` | `page/form` | `FormPage` (header `nowo_seo_kit.admin.settings.title` / `.intro`, admin navigation as header `tabs`; form = `SeoSiteSettingsType`; submit `nowo_seo_kit.admin.save`; `formThemes` = `web_ui.form_themes`; `data-testid="seo-kit-admin-settings"`) | `nowo_ui_form_body` (REQ-TWIG-003 child loop; the per-locale wording as [locale tabs](#locale-tabs) in a `section` fieldset before `organisationLegalName`, then the organisation heading in the `card_title` slot), `nowo_ui_content` (the noindex notice in the `flash_warning` slot when the site is not indexable, then `parent()`) |
| Surfaces list (`surfaces_index`) | `admin/surfaces_index.html.twig` | `page/list` | `ListPage` (header `nowo_seo_kit.admin.surfaces.title` / `.intro`, admin navigation as header `tabs`; `filters` = `SeoSurfaceLocaleFilterType` (GET `?locale=`); columns `surface` (required), `path` (priority 2), `title`, `problems`; rows = one array per audit subject: `id`, `surface`, `path`, `title`, `problems` (audit codes), `has_override`; row action Edit (`edit`, GET); `EmptyState` `nowo_seo_kit.admin.surfaces.empty_title` / `.empty`; caption `nowo_seo_kit.admin.surfaces.caption` (locale); actions column `nowo_seo_kit.admin.surfaces.column.actions`; `data-testid="seo-kit-admin-surfaces"`) | `nowo_ui_list_row` (surface key and path in the `code` slot, Override in `badge_info`, empty title as `table_cell_muted`, problems as `badge_warning` / `badge_danger` (`published_but_noindex`) with `nowo_seo_kit.admin.surfaces.problem.<code>` labels, `badge_success` when clean), `nowo_ui_content` (the site-wide noindex notice in the `flash_info` slot, then `parent()`) |
| Surface override form (`surfaces_form`) | `admin/surfaces_form.html.twig` | `page/form` | `FormPage` (header `nowo_seo_kit.admin.surfaces.edit_title` (surface key) / `.edit_intro` (locale), back link and cancel = the surfaces list of the locale; form = `SeoSurfaceType`; submit `nowo_seo_kit.admin.save`; `formThemes` = `web_ui.form_themes`; `data-testid="seo-kit-admin-surface-form"`) | none (scaffold default `nowo_ui_form_body`: REQ-TWIG-003 child loop) |

## Shared page context

Variables passed next to `page` (1.x names, kept for templates set through `web_ui.templates.<page>` or overridden
under `templates/bundles/NowoSeoKitBundle/`):

| Page | Variables |
| --- | --- |
| `settings` | `form` (the `FormView` of `page.form`), `settings` (`SeoSiteSettings`), `enabled_locales` (default locale first), `default_locale` |
| `surfaces_index` | `locale`, `locales`, `rows` (`list<SeoAuditRow>`), `site_indexable` |
| `surfaces_form` | `form` (the `FormView` of `page.form`), `surface` (`SeoSurface`), `surface_key`, `locale`, `locales` |

## Locale tabs

`admin/settings.html.twig` renders the `defaultTitle_<locale>`, `homeTitle_<locale>`, `defaultDescription_<locale>`
and `organisationDescription_<locale>` fields (`form.vars.localised_fields`) with the FormKit include:

```twig
{{ include('@NowoFormKitBundle/form/_locale_tabs.html.twig', {
    field: form, fields: form.vars.localised_fields, locales: enabled_locales, default_locale: default_locale,
    tabs_id: 'nowo-seo-kit-settings-wording', testid: 'nowo-seo-kit-settings-wording',
}, with_context: false) }}
```

- Renderer: the UiKit `locale_tabs` partial (UiKit is registered with the admin UI).
- DOM ids: tab `tab-nowo-seo-kit-settings-wording-<locale>`, panel `tab-nowo-seo-kit-settings-wording-<locale>-panel`.
- The default locale is listed first and marked; the first tab with a validation error opens.
- Labels: one key per field for every locale, `nowo_seo_site.<field>.label` with `%locale%` (since 1.19; the 1.x
  `nowo_seo_site.<field>_en|_es.label` keys are gone).
- Without JavaScript every locale is a stacked `<fieldset>`; `nowo-ui-tabs.js` (printed by the UiKit `scripts`
  partial of the layout) upgrades them to tabs.

## Unique pages

None: every admin page extends a UiKit scaffold.

## Per-page overrides

- `nowo_seo_kit.web_ui.templates.<page>` (ids in the [Pages](#pages) table): a host template that extends
  `@!NowoSeoKitBundle/<template>` and overrides a data block (`nowo_ui_list_row`, `nowo_ui_form_body`,
  `nowo_ui_form_aside`, `nowo_ui_list_after`, …). Prefer theming the UiKit slots (`nowo_ui_kit.theme`) and scaffolds
  once for every bundle.
- `templates/bundles/NowoSeoKitBundle/<subpath>` still overrides any bundle template (REQ-TWIG-001), but freezes it:
  prefer `web_ui.templates` (see [USAGE.md](USAGE.md#overriding-templates-req-twig-001)).

## Bundle partials

None. The bundle ships no local pagination, flash, confirm, modal, page header, filters or empty-state partial (the
1.x `admin/_flashes.html.twig` was removed in 1.19): the scaffolds and the UiKit partials (`action`, `list_filters`,
`table`, `empty`, `flashes`, `locale_tabs`) render them. The bundle ships no CSS and no JavaScript.

## Navigation

`Nowo\SeoKitBundle\Ui\SeoKitAreaNav` publishes the area `seo_kit` to the UiKit area-nav registry (SEO settings | Page
SEO, the enabled pages only; null for users the access checker refuses): `nowo_ui_area_nav('seo_kit')` mounts it in a host menu (DashboardMenu). The pages render
the same navigation as page-header tabs when both pages are enabled (fallback for hosts without DashboardMenu).

## Allowed exceptions

- (none) — the contract test declares no `allowedClasses()`.

## Head template

| Template | Variables | Notes |
| --- | --- | --- |
| `seo/head.html.twig` | head context | `{{ nowo_seo_head() }}`; override through `nowo_seo_kit.templates.head` |
