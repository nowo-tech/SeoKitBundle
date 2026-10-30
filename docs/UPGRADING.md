# Upgrading

## Table of contents

- [Unreleased](#unreleased)
  - [Configuration: `admin` → `web_ui` + `security`](#configuration-admin--web_ui--security)
  - [Template contract](#template-contract)
  - [Security changes](#security-changes)
- [To 1.18.0](#to-1180)
- [To 1.17.0](#to-1170)
- [To 1.16.1](#to-1161)
- [To 1.16.0](#to-1160)
  - [JSON surface API needs an `X-CSRF-Token` header](#json-surface-api-needs-an-x-csrf-token-header)
  - [Optional table prefix](#optional-table-prefix)
- [To 1.15.0](#to-1150)
  - [Locale tabs on `/settings/seo`](#locale-tabs-on-settingsseo)
- [To 1.14.1](#to-1141)
- [To 1.14.0](#to-1140)
- [To 1.13.0](#to-1130)
- [To 1.12.1](#to-1121)
- [To 1.12.0](#to-1120)
  - [What changed](#what-changed)
  - [Breaking changes](#breaking-changes)
- [To 1.11.1](#to-1111)
  - [What changed](#what-changed-1)
  - [Breaking changes](#breaking-changes-1)
- [To 1.11.0](#to-1110)
  - [What changed](#what-changed-2)
  - [Breaking changes](#breaking-changes-2)
- [To 1.10.2](#to-1102)
- [To 1.10.1](#to-1101)
  - [What changed](#what-changed-3)
  - [Breaking changes](#breaking-changes-3)
- [To 1.10.0](#to-1100)
  - [What changed](#what-changed-4)
  - [Integrator actions](#integrator-actions)
  - [Breaking changes](#breaking-changes-4)
- [To 1.9.0](#to-190)
  - [What changed](#what-changed-5)
  - [Breaking changes](#breaking-changes-5)
- [To 1.8.1](#to-181)
  - [Fixed](#fixed)
  - [Added](#added)
  - [Breaking changes](#breaking-changes-6)
- [To 1.8.0](#to-180)
  - [What changed](#what-changed-6)
  - [Breaking changes](#breaking-changes-7)
- [To 1.7.0](#to-170)
  - [Breaking changes](#breaking-changes-8)
- [To 1.6.0](#to-160)
  - [Optional Doctrine persistence](#optional-doctrine-persistence)
  - [Optional audit CLI](#optional-audit-cli)
  - [Typed JSON-LD helpers](#typed-json-ld-helpers)
  - [Breaking changes](#breaking-changes-9)
- [To 1.5.0](#to-150)
  - [Optional configuration](#optional-configuration)
  - [Optional services (autoconfigured tags)](#optional-services-autoconfigured-tags)
  - [Runtime extras](#runtime-extras)
  - [Breaking changes](#breaking-changes-10)
- [From 1.4.2 to 1.4.3](#from-142-to-143)
- [To 1.4.2](#to-142)
- [To 1.4.1](#to-141)
- [To 1.4.0](#to-140)
  - [Twig Extra Bundle (REQ-TWIG-004)](#twig-extra-bundle-req-twig-004)
  - [Twig-CS-Fixer (maintainers)](#twig-cs-fixer-maintainers)
- [To 1.3.1](#to-131)
  - [Install / update](#install--update)
- [To 1.3.0](#to-130)
  - [SeoPathBuilderInterface (non-breaking)](#seopathbuilderinterface-non-breaking)
- [To 1.2.0](#to-120)
  - [Twig namespace rename](#twig-namespace-rename)
  - [Breaking changes](#breaking-changes-11)
- [To 1.1.0](#to-110)
  - [Hyphenated slug keys](#hyphenated-slug-keys)
  - [Demo only](#demo-only)
  - [Install](#install)
- [To 1.0.0](#to-100)
  - [Install](#install-1)
  - [Configuration](#configuration)
  - [Twig](#twig)
  - [Breaking changes](#breaking-changes-12)

## Unreleased

From **1.18.x**:

```bash
composer update nowo-tech/seo-kit-bundle nowo-tech/ui-kit-bundle
```

Nothing changes while the admin UI stays off (`web_ui.enabled` / `admin.enabled` false, the default).

### Configuration: `admin` → `web_ui` + `security`

The `admin` keys still work and are mapped (with a deprecation); move to the new names before 2.0:

```yaml
# Before (1.x)
nowo_seo_kit:
    admin:
        enabled: true
        role: ROLE_ADMIN
        settings_template: 'admin/seo/settings.html.twig'

# After
nowo_seo_kit:
    web_ui:
        enabled: true
        # path_prefix: /admin/seo        # optional: /admin/seo/settings, /admin/seo/surfaces (null keeps /settings/seo, /admin/seo/surfaces)
        templates:
            settings: 'admin/seo/settings.html.twig'
    security:
        access_roles: [ROLE_ADMIN]
        # access_checker: App\Security\SeoAccessChecker
```

| 1.x key | Replacement |
| --- | --- |
| `admin.enabled` / `settings` / `surfaces` / `api_enabled` / `api_csrf_protection` | `web_ui.<same key>` |
| `admin.role` | `security.access_roles` (a list) |
| `admin.settings_template`, `admin.surfaces_index_template`, `admin.surfaces_form_template` | `web_ui.templates.settings`, `.surfaces_index`, `.surfaces_form` |

- The admin UI now needs **SecurityBundle** (the container does not compile without it) unless
  `security.allow_unauthenticated: true` (demo only), and an empty role list needs a custom `security.access_checker`.
- `admin.role: PUBLIC_ACCESS` (or an empty role) is refused: use `allow_unauthenticated` for a demo.
- Add `access_control` for the admin paths and `^/_nowo/seo/` (see
  [CONFIGURATION.md — Host access_control](CONFIGURATION.md#host-access_control)).

### Template contract

- `admin/settings.html.twig`, `admin/surfaces_index.html.twig` and `admin/surfaces_form.html.twig` are no longer
  standalone `<!DOCTYPE html>` pages: they extend the UiKit form / list scaffolds and receive a `page` view model (the
  1.x variables are still passed). They render inside `nowo_ui_kit.layouts.admin` (or `web_ui.layout_template`): set
  it to your admin layout or a one-file bridge — [CONFIGURATION.md — Host layout and CSS stack](CONFIGURATION.md#host-layout-and-css-stack).
- **Overrides of these templates** (`templates/bundles/NowoSeoKitBundle/admin/…` or `admin.*_template`) keep working
  but freeze the 1.x markup. Recommended: delete them and theme UiKit once; when a page really needs a change, use
  `web_ui.templates.<page>` with a template that extends `@!NowoSeoKitBundle/admin/<page>.html.twig` and overrides a
  data block (`nowo_ui_form_body`, `nowo_ui_list_row`, …) — see [TEMPLATES.md](TEMPLATES.md).
- `admin/_flashes.html.twig` is removed (flashes come from the UiKit `flashes` partial of the layout). Remove an
  override of it.
- Data blocks, view-model fields and page ids are part of the contract from now on: renames will be listed here.
- Labels of the localised settings fields: `nowo_seo_site.default_title.label`, `home_title`, `default_description`,
  `organisation_description` with `%locale%` replace `nowo_seo_site.<field>_en|_es.label`; move app translation
  overrides of the old keys.
- The seeded FormKit profile `seo_kit` no longer adds `form-control` / `form-select` / `mb-3` classes (the UiKit form
  theme does, per `nowo_ui_kit.css_framework`). A host that renders `SeoSurfaceType` / `SeoSiteSettingsType` in its own
  Bootstrap pages can define `nowo_form_kit.profiles.seo_kit` itself (the bundle then seeds nothing).

### Security changes

- Image URLs of the site settings (default Open Graph image, organisation logo) must be a site path or an https URL;
  stored values are only re-validated on the next save.
- Canonical / image URLs with inner control characters or whitespace are rejected.
- Title / description templates no longer receive request attributes named like nonces, tokens, CSRF values, secrets
  or passwords (`{csp_nonce}` stays as literal text).

## To 1.18.0

From **1.17.0**:

```bash
composer update nowo-tech/seo-kit-bundle
```

Nothing yet.

## To 1.17.0

- New opt-in `x_robots_tag.enabled` (default `false`): nothing changes unless you turn it on. Enabled, every main
  response gets `X-Robots-Tag` with the page's resolved robots value.

## To 1.16.1

From **1.16.0**:

```bash
composer update nowo-tech/seo-kit-bundle
```

## To 1.16.0

From **1.15.0**:

```bash
composer update nowo-tech/seo-kit-bundle
```

### JSON surface API needs an `X-CSRF-Token` header

`POST /_nowo/seo/surfaces/{key}/{locale}` now validates a CSRF token sent in the `X-CSRF-Token` header (REQ-SEC-005). Requests without it get **403**.

- Read the token from the `csrf_token` field of `GET /_nowo/seo/surfaces/{key}/{locale}` (or render `csrf_token('nowo_seo_kit_surface_api')` in Twig) and send it with every `POST`:

  ```js
  const { csrf_token } = await (await fetch(url, { credentials: 'same-origin' })).json();
  await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf_token },
      body: JSON.stringify({ metaTitle: 'Home' }),
  });
  ```

- The host needs `symfony/security-csrf` and `framework.csrf_protection` enabled; without a token manager every `POST` is refused (fail-closed).
- API clients authenticated only by a Bearer / API token (no session cookie) can opt out with `nowo_seo_kit.admin.api_csrf_protection: false`.

### Optional table prefix

New `nowo_seo_kit.doctrine.table_prefix` (default `''`, no change). Setting it renames the SeoKit tables (e.g. `app_nowo_seo_surface`): generate and review a Doctrine migration before deploying. See [CONFIGURATION.md — doctrine](CONFIGURATION.md#doctrine).

## To 1.15.0

From **1.14.1**:

```bash
composer update nowo-tech/seo-kit-bundle
```

### Locale tabs on `/settings/seo`

- Composer: `nowo-tech/form-kit-bundle` **^2.13** and the new requirement `nowo-tech/ui-kit-bundle` **^2.5.1**.
  Symfony Flex registers `NowoUiKitBundle`; without Flex add it to `config/bundles.php` (without it the tabs fall
  back to the neutral FormKit markup, which needs a `tabs` Stimulus controller or `nowo-ui-tabs.js` to switch).
- **Template**: `@NowoSeoKitBundle/admin/settings.html.twig` no longer prints one `<fieldset><legend>XX</legend>` per
  locale. The per-locale wording renders through
  `include('@NowoFormKitBundle/form/_locale_tabs.html.twig', {field: form, fields: form.vars.localised_fields, …})`.
  New DOM ids: tab `tab-nowo-seo-kit-settings-wording-<locale>`, panel
  `tab-nowo-seo-kit-settings-wording-<locale>-panel`, wrapper `data-testid="nowo-seo-kit-settings-wording"`.
  E2E selectors on the old fieldsets need updating; the input names (`nowo_seo_site[defaultTitle_es]`…) are
  unchanged. Overrides of `settings.html.twig` (`nowo_seo_kit.admin.settings_template`) keep working; copy the
  include to get the tabs.
- When UiKit is registered the default template links `css/nowo-ui.css` and `js/nowo-ui-tabs.js` from the
  `nowo_ui_kit` asset package (run `assets:install`). Host layouts that already print the UiKit `scripts` partial
  need nothing else.
- No data-model change.

## To 1.14.1

From **1.14.0** — security - raw JSON-LD fully escaped (< and >); deprecation version notes corrected.

```bash
composer update nowo-tech/seo-kit-bundle
```

- No application upgrade steps. Raw `json_ld.json` strings now have every `<` and `>` escaped (`\u003C`/`\u003E`), which JSON parsers treat identically.

## To 1.14.0

From **1.13.0** — complete translations, CSP nonce support in nowo_seo_head, legacy route files deprecated, page_head docs.

```bash
composer update nowo-tech/seo-kit-bundle
```

- If your app imports `@NowoSeoKitBundle/Resources/config/routes.yaml` or `routes_admin.yaml`, switch to the route loaders (`resource: .` with `type: nowo_seo_kit`, and `type: nowo_seo_kit_admin` for the admin); both files are deprecated and will be removed in 2.0. The loaders honour `sitemap.*`, `robots.*` and `admin.*` toggles.
- `nowo_seo_head()` accepts an optional CSP nonce and falls back to the request attribute `csp_nonce`. If your CSP listener already sets that attribute, the JSON-LD `<script>` now carries `nonce="…"` automatically.

## To 1.13.0

From **1.12.1** — security and fixes - surface API requires JSON, hreflang/robots/sitemap correctness, PHP 8.4/8.5 and Symfony 8 tested.

```bash
composer update nowo-tech/seo-kit-bundle
```

- `POST /_nowo/seo/surfaces/{key}/{locale}` now requires `Content-Type: application/json`; form-encoded posts get 415.
- `indexable: false` now overrides page/provider robots values; noindex pages are dropped from the sitemap; hreflang no longer emits `?_locale=` alternates.
- Symfony 7.0–7.3 are formally dropped (they were not installable anyway); Symfony 8.0/8.1 on PHP 8.4/8.5 are now tested in CI.

## To 1.12.1

From **1.12.0** — dependency refresh and repository maintenance.

```bash
composer update nowo-tech/seo-kit-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.12.0

From **1.11.1** — explicit `none` social profiles; hardened JSON-LD fallbacks in the default head template.

```bash
composer update nowo-tech/seo-kit-bundle
php bin/console cache:clear
```

### What changed

**No required application changes.**

- Site settings accept `none` in *Social profiles* to declare that the site has no social profiles. It is stored as-is, never printed in `sameAs`, and detectable with `SeoSiteConfig::hasNoSocialProfiles()`; `SeoSiteConfig::socialProfileUrls()` returns only http(s) URLs. Hosts that fall back to default profiles when the list is empty should check `hasNoSocialProfiles()` first.
- The bundled `seo/head.html.twig` now hex-escapes `<`, `>`, `&`, `'` and `"` in the `jsonLd.document` / `jsonLd.graph` fallbacks. If you override this template, apply the same `json_encode` flags (see `StructuredDataGraph::toJson()`).

### Breaking changes

None.

## To 1.11.1

From **1.11.0** — YAML PageHead defaults when `base_url` is set; optional GenerativeSeoKit companion.

```bash
composer require nowo-tech/seo-kit-bundle:^1.11.1
php bin/console cache:clear
```

### What changed

**No required application changes.**

Optional companion: [`nowo-tech/generative-seo-kit-bundle`](https://packagist.org/packages/nowo-tech/generative-seo-kit-bundle) for GEO (`llms.txt`, AI crawler robots via `GeoRobotsGroupsProviderInterface`). Do not duplicate the same User-agents in `nowo_seo_kit.robots.groups`.

When `base_url` is set, PageHead now has a YAML `ConfigPageHeadDefaultsProvider`. Hosts that already alias `PageHeadDefaultsProviderInterface` keep that alias.

### Breaking changes

None.

## To 1.11.0

From **1.10.2** — extra robots.txt groups and LocalBusiness / content JSON-LD nodes.

```bash
composer require nowo-tech/seo-kit-bundle:^1.11
php bin/console cache:clear
```

### What changed

**No required application changes.** Optional GEO robots groups:

```yaml
nowo_seo_kit:
    robots:
        groups:
            - user_agent: GPTBot
              allow: ['/']
            - user_agent: Google-Extended
              disallow: ['/']
```

Or implement `GeoRobotsGroupsProviderInterface` (tag `nowo_seo_kit.geo_robots_groups_provider`). Extra groups are omitted when the site is not indexable.

New JSON-LD helpers: `LocalBusinessNode`, `PersonNode`, `FaqPageNode`, `BlogPostingNode` (pass real schema.org types; do not invent medical types).

Maintainers using `make igor` / `composer igor`: require-dev Igor is now `^0.10.1`. After upgrading the lockfile, clear Symfony cache if you run Igor against a host app (`php bin/console cache:clear`).

### Breaking changes

None.

## To 1.10.2

From **1.10.1** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/seo-kit-bundle
php bin/console cache:clear
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

## To 1.10.1

From **1.10.0** — FrankenPHP worker mode without kernel/`services_resetter` reset (scenario B).

```bash
composer require nowo-tech/seo-kit-bundle:^1.10.1
```

### What changed

**No required application changes.** Behaviour to be aware of:

1. `SeoRuntime`, `PageHeadContext` and the `SeoSiteConfigProvider` memo are cleared on `kernel.request`
   (priority 4096, main requests only). Call `SeoRuntime::set()` / `PageHeadContext::describe()` from controllers or
   from `kernel.request` listeners with a priority **lower than 4096**, not earlier.
2. `SeoRuntimeClearSubscriber` gained two optional constructor arguments (`?PageHeadContext`, `?SeoSiteConfigProvider`);
   container users are not affected.
3. `SeoSiteSettingsRepository::getOrCreate()` refreshes the singleton row from the database (one extra query on a
   `cache.app` miss). The `translations` association now cascades `refresh` (ORM-level only, no schema change).
4. Under FrankenPHP worker mode without `services_resetter`, clearing the application's EntityManager between requests
   remains the application's responsibility.

See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

### Breaking changes

None.

## To 1.10.0

From **1.9.x** — PageHead pipeline for CMS hosts.

```bash
composer require nowo-tech/seo-kit-bundle:^1.10
```

### What changed

- **`PageHead` resolution** (`PageHeadInput` → `PageHeadResolver` → `PageHead`) with SPI:
  - `PageHeadDefaultsProviderInterface` (required for PageHead)
  - optional `PageHeadSiteGraphProviderInterface`, `PageHeadTitleComposerInterface`
- `PageHeadContext` + `PageHeadRuntimeBridge` for request-scoped head + Twig runtime
- `HreflangSet` / `HreflangSetBuilder`, `SeoPencilCatalogFactory`
- Config `nowo_seo_kit.page_head.open_graph_regions` (requires `base_url`)
- `SeoSurfaceType` is extendable so hosts can keep a custom form block prefix

### Integrator actions

1. Set `nowo_seo_kit.base_url` and wire `PageHeadDefaultsProviderInterface`.
2. Optionally alias title composer / site JSON-LD providers.
3. Remove host duplicates of PageHead / hreflang / AbsoluteUrlBuilder / pencil catalog builders.
4. Prefer `nowo:seo:audit` over a host `app:seo:audit` alias.

### Breaking changes

None for hosts that do not use PageHead. Hosts that already consumed a path/dev copy of these classes should drop their forks and use the kit namespaces.

## To 1.9.0

From **1.8.x** — FormKit integration for admin forms.

```bash
composer require nowo-tech/seo-kit-bundle:^1.9
# FormKit is a hard dependency; Flex registers NowoFormKitBundle when present.
```

### What changed

- Admin forms use FormKit profile **`seo_kit`** (labels/help via `nowo_seo_site.*` / `nowo_seo_surface.*` in `NowoSeoKitBundle`)
- Requires `nowo-tech/form-kit-bundle` ^2.4 (Symfony form stack ≥ 7.4)

Hosts may override the `seo_kit` profile in `nowo_form_kit.yaml`; SeoKit only prepends it when missing.

### Breaking changes

- Installing SeoKit without FormKit is no longer supported (composer will pull FormKit).
- Symfony **7.0–7.3** no longer satisfy FormKit’s constraints; use **7.4+** or **8.x**.

## To 1.8.1

From **1.8.0** — bugfix + small helper.

```bash
composer require nowo-tech/seo-kit-bundle:^1.8.1
```

### Fixed

`nowo:seo:audit` now receives tagged `nowo_seo_kit.audit_subject_provider` services again. Remove any host `services.yaml` bind of `SeoAuditor::$subjectProviders` if you added one as a workaround.

### Added

`AbsoluteUrlBuilder` when `base_url` is set — prefer it (or wrap it) instead of a host-only canonical URL helper.

### Breaking changes

None.

## To 1.8.0

From **1.7.x** — additive helpers for CMS / blog surface keys.

```bash
composer require nowo-tech/seo-kit-bundle:^1.8
```

### What changed

- **`SeoSurfaceKeys`** — `page:{pageKey}` / `blog:{id}` builders + `isPage` / `pageKey`
- **`SeoSurfaceManager`** — `find` / `getOrCreate` / `saveOrClear` / `isEmpty` (registered when `persistence.enabled`)

Hosts that still store page SEO as a Doctrine FK entity should migrate rows into `nowo_seo_surface` with `surface_key = CONCAT('page:', page_key)` and drop the legacy table.

### Breaking changes

None.

## To 1.7.0

From **1.6.x** — additive / opt-in admin.

```bash
composer require nowo-tech/seo-kit-bundle:^1.7
```

```yaml
nowo_seo_kit:
    persistence:
        enabled: true
    admin:
        enabled: true
        settings: true   # /settings/seo
        surfaces: true   # /admin/seo/surfaces
        api_enabled: true
        # Override Twig for branded chrome:
        # settings_template: 'admin/seo/settings.html.twig'
```

Import admin routes (once):

```yaml
# config/routes/nowo_seo_kit.yaml
nowo_seo_kit_admin:
    resource: .
    type: nowo_seo_kit_admin
```

### Breaking changes

None when `admin.enabled` stays `false` (default).

## To 1.6.0

From **1.5.x** — additive / opt-in. **No required migration** unless you enable persistence or the audit CLI.

```bash
composer require nowo-tech/seo-kit-bundle:^1.6
php bin/console cache:clear
```

### Optional Doctrine persistence

```bash
composer require doctrine/orm doctrine/doctrine-bundle
```

```yaml
# config/packages/nowo_seo_kit.yaml
nowo_seo_kit:
    persistence:
        enabled: true
        # fallback_robots: 'index, follow'
        # fallback_site_name: ''
        # fallback_contact_email: ''
        # register_audit_command: true
```

Then run your usual Doctrine migrations for:

- `nowo_seo_site_settings`
- `nowo_seo_site_settings_translation`
- `nowo_seo_surface`

When enabled, the bundle prepends ORM attribute mappings and registers `DoctrineSeoDefaultsProvider` + `SeoSiteConfigProvider`.

### Optional audit CLI

`nowo:seo:audit` is registered when `persistence.register_audit_command` is `true` (default). Hosts can tag `SeoAuditSubjectProviderInterface` services as `nowo_seo_kit.audit_subject_provider`.

```bash
php bin/console nowo:seo:audit
```

### Typed JSON-LD helpers

Optional PHP API under `Nowo\SeoKitBundle\Model\StructuredData\` and `SiteStructuredDataFactory` — no YAML change required.

### Breaking changes

None for default configuration (`persistence.enabled: false`).

## To 1.5.0

From **1.4.x** — additive features. **No required migration** unless you adopt the new options.

```bash
composer require nowo-tech/seo-kit-bundle:^1.5
php bin/console cache:clear
```

### Optional configuration

| Key | Purpose |
| --- | --- |
| `indexable: false` | Site-wide noindex + disallow `/` + empty/404 sitemap |
| `defaults.verification.google` / `bing` | Webmaster meta tags |
| `defaults.open_graph.image_width` / `image_height` / `image_alt` / `locale_alternates` | Richer OG tags |
| `templates.head_includes_title: false` | Host layout owns `<title>` |

### Optional services (autoconfigured tags)

Implement and register (or rely on `#[AutoconfigureTag]`):

- `SeoDefaultsProviderInterface` → `nowo_seo_kit.defaults_provider`
- `SiteIndexabilityProviderInterface` → `nowo_seo_kit.indexability_provider`
- `SitemapUrlProviderInterface` → `nowo_seo_kit.sitemap_url_provider`

### Runtime extras

`SeoRuntime::set()` may include `title_final`, `alternates`, and `json_ld.json` / `json_ld.document` for hosts that precompose title or JSON-LD.

### Breaking changes

None for default YAML. New Open Graph / JSON-LD array shapes are additive (`image_*`, `locale_alternates`, `json` / `document`).

## From 1.4.2 to 1.4.3

No breaking changes. **No application upgrade steps.**

```bash
composer update nowo-tech/seo-kit-bundle
```

## To 1.4.2

From **1.4.1** — No application upgrade steps.

```bash
composer update nowo-tech/seo-kit-bundle
```

## To 1.4.1

From **1.4.0** — No application upgrade steps. **Demos only:** Hot Reload Bundle `^1.4` (FrankenPHP Mercure/`hot_reload`, `dev`/`test`).

```bash
composer update nowo-tech/seo-kit-bundle
```

## To 1.4.0

From **1.3.1** — Adds required Twig Extra (REQ-TWIG-004) and Twig-CS-Fixer. Register TwigExtraBundle if Flex did not.

```bash
composer update nowo-tech/seo-kit-bundle
php bin/console cache:clear
```

### Twig Extra Bundle (REQ-TWIG-004)

Hosts that render this bundle's Twig templates must install:

```bash
composer require twig/extra-bundle twig/string-extra
```

and enable `Twig\Extra\TwigExtraBundle\TwigExtraBundle`. Flex recipes usually register it automatically.

### Twig-CS-Fixer (maintainers)

Package maintainers: `composer twig:lint` / `composer twig:fix` use `.twig-cs-fixer.php` over `src/` (and `templates/` when present).

## To 1.3.1

Documentation and maintainer dependency bumps only. **No config or API migration** for integrators.

### Install / update

```bash
composer require nowo-tech/seo-kit-bundle:^1.3.1
php bin/console cache:clear
```

Twig override guidance (freeze rule, prefer `templates.head`) is in [USAGE.md](USAGE.md#overriding-templates-req-twig-001). Hosts already on **1.3.0** need no further changes.

## To 1.3.0

### SeoPathBuilderInterface (non-breaking)

`SeoPathBuilder` now implements `SeoPathBuilderInterface`, and the container exposes an alias for decoration (e.g. RoutingKit path overrides for canonical/hreflang).

**No action required** for typical consumers. Continue requiring `nowo-tech/seo-kit-bundle: ^1.2` or bump to `^1.3`.

Optional: type-hint / decorate `Nowo\SeoKitBundle\Service\SeoPathBuilderInterface` instead of the concrete class.

```bash
composer require nowo-tech/seo-kit-bundle:^1.3
php bin/console cache:clear
```

## To 1.2.0

### Twig namespace rename

Logical Twig names moved from `@NowoSeoKit/...` to `@NowoSeoKitBundle/...`.

1. If you set `nowo_seo_kit.templates.head` explicitly, update it to `@NowoSeoKitBundle/seo/head.html.twig` (or your custom path).
2. Move application overrides from `templates/bundles/NowoSeoKit/` to `templates/bundles/NowoSeoKitBundle/`.
3. Clear cache: `php bin/console cache:clear`

```bash
composer require nowo-tech/seo-kit-bundle:^1.2
php bin/console cache:clear
```

### Breaking changes

- Default Twig namespace and default `templates.head` value changed (`NowoSeoKit` → `NowoSeoKitBundle`).
- Override directory path changed to match Symfony’s `BundleName` convention.

## To 1.1.0

### Hyphenated slug keys

`slugs.<route>.<slug>` keys with hyphens (e.g. `hello-world`) are **preserved**. Previously Symfony Config could normalize them to underscores (`hello_world`), which broke path building and sitemap entries.

If your deployed config already used underscore keys because of that normalization, either:

- rename keys to the real URL slug (`hello-world`), or
- keep underscore keys only if that is the slug you pass in the route.

No other breaking API changes. New helper: `SeoPathBuilder::resolveCanonicalSlug()`.

### Demo only

The FrankenPHP demo gained a locale switch, `/` as default-locale home, and `/admin/seo` CRUD. These are not required for integrators using the bundle in an application.

### Install

```bash
composer require nowo-tech/seo-kit-bundle:^1.1
```

## To 1.0.0

First public release. No prior versions.

### Install

```bash
composer require nowo-tech/seo-kit-bundle:^1.0
```

### Configuration

Ensure `config/packages/nowo_seo_kit.yaml` exists (Flex recipe or copy from `.symfony/recipe/`). Import routes via `config/routes/nowo_seo_kit.yaml`.

### Twig

Add `{{ nowo_seo_head() }}` to your base layout if not already present.

### Breaking changes

None (initial release).
