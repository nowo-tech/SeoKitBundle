# Templates

Admin templates of SeoKitBundle (opt-in admin, `nowo_seo_kit.admin.*`). They are minimal standalone pages: hosts
override them through `nowo_seo_kit.admin.settings_template` / `surfaces_*_template` or
`templates/bundles/NowoSeoKitBundle/admin/…`.

## Table of contents

- [Pages](#pages)
- [Locale tabs](#locale-tabs)

## Pages

| Page | Template | Variables | Notes |
| --- | --- | --- | --- |
| Site settings (`/settings/seo`) | `admin/settings.html.twig` | `form`, `settings`, `enabled_locales`, `default_locale` | per-locale wording as locale tabs (below) |
| Surfaces list | `admin/surfaces_index.html.twig` | `locale`, `locales`, `rows`, `site_indexable` | one page per locale (link navigation) |
| Surface edit | `admin/surfaces_form.html.twig` | `form`, `surface`, `surface_key`, `locale`, `locales` | one record per locale: link navigation, no tabs |
| Flash messages | `admin/_flashes.html.twig` | `app.flashes` | included by every admin page |
| Head tags | `seo/head.html.twig` | head context | `{{ nowo_seo_head() }}` |

## Locale tabs

`admin/settings.html.twig` renders the `defaultTitle_<locale>`, `homeTitle_<locale>`, `defaultDescription_<locale>`
and `organisationDescription_<locale>` fields (`form.vars.localised_fields`) with the FormKit include (since 1.15):

```twig
{{ include('@NowoFormKitBundle/form/_locale_tabs.html.twig', {
    field: form, fields: form.vars.localised_fields, locales: enabled_locales, default_locale: default_locale,
    tabs_id: 'nowo-seo-kit-settings-wording', testid: 'nowo-seo-kit-settings-wording',
}, with_context: false) }}
```

- Renderer: the UiKit `locale_tabs` partial when `NowoUiKitBundle` is registered, else the neutral FormKit markup.
- DOM ids: tab `tab-nowo-seo-kit-settings-wording-<locale>`, panel `tab-nowo-seo-kit-settings-wording-<locale>-panel`.
- The default locale is listed first and marked; the first tab with a validation error opens.
- Without JavaScript every locale is a stacked `<fieldset>`; `nowo-ui-tabs.js` (linked by the default template when
  UiKit is registered) upgrades them to tabs.
