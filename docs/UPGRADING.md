# Upgrading

## Table of contents

- [To 1.10.1](#to-1101)
- [To 1.10.0](#to-1100)
- [To 1.9.0](#to-190)
- [To 1.8.1](#to-181)
- [To 1.8.0](#to-180)
- [To 1.7.0](#to-170)
- [To 1.6.0](#to-160)
- [To 1.5.0](#to-150)
- [From 1.4.2 to 1.4.3](#from-142-to-143)
- [To 1.4.2](#to-142)
- [To 1.4.1](#to-141)
- [To 1.4.0](#to-140)
- [To 1.3.1](#to-131)
- [To 1.3.0](#to-130)
- [To 1.2.0](#to-120)
- [To 1.1.0](#to-110)
- [To 1.0.0](#to-100)

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
