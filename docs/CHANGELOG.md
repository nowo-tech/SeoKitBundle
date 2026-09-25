# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.10.1] - 2026-09-25](#1101---2026-09-25)
- [[1.10.0] - 2026-09-22](#1100---2026-09-22)
- [[1.9.0] - 2026-09-22](#190---2026-09-22)
- [[1.8.1] - 2026-09-22](#181---2026-09-22)
- [[1.8.0] - 2026-09-22](#180---2026-09-22)
- [[1.7.0] - 2026-09-22](#170---2026-09-22)
- [[1.6.0] - 2026-09-21](#160---2026-09-21)
- [[1.5.0] - 2026-09-21](#150---2026-09-21)
- [[1.4.3] - 2026-08-24](#143---2026-08-24)
- [[1.4.2] - 2026-08-19](#142---2026-08-19)
- [[1.4.1] - 2026-08-18](#141---2026-08-18)
- [[1.4.0] - 2026-08-04](#140---2026-08-04)
- [[1.3.1] - 2026-07-30](#131---2026-07-30)
- [[1.3.0] - 2026-07-29](#130-2026-07-29)
- [[1.2.0] - 2026-07-22](#120-2026-07-22)
- [[1.1.0] - 2026-07-18](#110-2026-07-18)
- [[1.0.0] - 2026-07-16](#100-2026-07-16)

## [Unreleased]

## [1.10.1] - 2026-09-25

### Fixed

- **FrankenPHP worker (no kernel reset):** `SeoRuntimeClearSubscriber` now also listens to `kernel.request`
  (priority 4096, main requests only) and clears `SeoRuntime`, `PageHeadContext` and the `SeoSiteConfigProvider` memo
  before controllers run, so a page that does not call `describe()` no longer renders the previous page head and site
  settings saved in another worker are picked up on the next request (through `cache.app`).
- `SeoRuntime` implements `ResetInterface` (tagged `kernel.reset` via autoconfiguration).
- Repository flushes reset a closed EntityManager before rethrowing, and `getOrCreate()` of `SeoSiteSettingsRepository` /
  `SeoSurfaceRepository` returns the row created by a concurrent request after a unique-constraint violation.
- `SeoSiteSettingsRepository::getOrCreate()` refreshes an already managed singleton row (translations cascade
  `refresh`), so a stale identity map copy is never snapshotted into the shared cache.
- Fixed PHPStan findings (0 errors at level 8).

### Documentation

- Added [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md) (scenario B viable after remediation).

### Notes

- Branch alias `dev-main` → `1.10.x-dev`.
- Under scenario B, clearing the application's EntityManager identity map between requests remains the host's responsibility.

## [1.10.0] - 2026-09-22

### Added

- **`PageHead` pipeline** — `PageHeadInput` / `PageHead` / `PageHeadDefaults`, `PageHeadResolver`, SPI (defaults / site graph / title composer), `PageHeadContext`, `PageHeadRuntimeBridge`
- **`HreflangSet` / `HreflangSetBuilder`**
- **`SeoPencilCatalogFactory`** for public SEO pencil JSON catalogs
- Config `page_head.open_graph_regions` (requires `base_url`)

### Notes

- Branch alias `dev-main` → `1.10.x-dev`.

## [1.9.0] - 2026-09-22

### Changed

- **FormKit:** admin forms (`SeoSiteSettingsType`, `SeoSurfaceType`) extend `FormKitAbstractType` via `AbstractSeoFormType` with profile `seo_kit`; depends on `nowo-tech/form-kit-bundle` (^2.4)
- Composer now requires `symfony/form`, `symfony/validator`, and `symfony/translation` (Symfony form stack effectively ≥ 7.4)

### Notes

- Register `Nowo\FormKitBundle\NowoFormKitBundle` (Flex) so admin labels resolve from `NowoSeoKitBundle` translations (`nowo_seo_site.*` / `nowo_seo_surface.*`).
- Hosts may override the `seo_kit` profile in `nowo_form_kit` config; SeoKit only prepends it when missing.
- Branch alias `dev-main` → `1.9.x-dev`.
- Breaking for hosts on Symfony **7.0–7.3** or without FormKit — see [UPGRADING.md](UPGRADING.md#to-190).

## [1.8.1] - 2026-09-22

### Fixed

- **`nowo:seo:audit` subject providers** — `registerAudit()` no longer overwrites the `services.yaml` `SeoAuditor` definition, which dropped `!tagged_iterator nowo_seo_kit.audit_subject_provider` and left hosts with zero surfaces

### Added

- **`AbsoluteUrlBuilder`** — origin + path normalisation for canonical / hreflang / OG (from `nowo_seo_kit.base_url`)

### Notes

- Hosts that bound `$subjectProviders` manually can remove that workaround after upgrading.
- Branch alias `dev-main` → `1.8.x-dev`.

## [1.8.0] - 2026-09-22

### Added

- **`SeoSurfaceKeys`** — conventional keys (`page:{key}`, `blog:{id}`) for CMS / blog pencils
- **`SeoSurfaceManager`** — `find` / `getOrCreate` / `saveOrClear` (empty overrides delete the row); registered when `persistence.enabled`

### Notes

- Hosts should store CMS page SEO as `page:{pageKey}` surfaces instead of a FK entity.
- Branch alias `dev-main` → `1.8.x-dev`.
- Additive: no behaviour change unless hosts adopt the helpers.

## [1.7.0] - 2026-09-22

### Added

- **Admin UI (opt-in):** `nowo_seo_kit.admin.enabled` registers site settings (`/settings/seo`) + surface overrides (`/admin/seo/surfaces`) with overrideable Twig templates
- **SEO pencil API:** `GET/POST /_nowo/seo/surfaces/{key}/{locale}` (`admin.api_enabled`)
- **Forms:** `SeoSiteSettingsType`, `SeoSurfaceType` + `OriginUrlGuard` for on-origin canonical / https OG validation
- **Routing:** `SeoAdminRouteLoader` (`type: nowo_seo_kit_admin`)

### Notes

- Admin requires `persistence.enabled: true`. Hosts should override kit Twig with branded layouts.
- Branch alias `dev-main` → `1.7.x-dev`.
- Additive / opt-in: default `admin.enabled: false` keeps 1.6.x behaviour.

## [1.6.0] - 2026-09-21

### Added

- **Doctrine persistence (opt-in):** `nowo_seo_kit.persistence.enabled` registers `SeoSiteSettings` / `SeoSiteSettingsTranslation` / `SeoSurface`, repositories, `SeoSiteConfigProvider`, and `DoctrineSeoDefaultsProvider` (SPI defaults + indexability)
- **Typed JSON-LD:** `Nowo\SeoKitBundle\Model\StructuredData\*` nodes + CSP-safe `StructuredDataGraph::toJson()`, plus `SiteStructuredDataFactory`
- **Audit CLI:** `nowo:seo:audit` with `SeoAuditRules` / `SeoAuditor` and host tag `nowo_seo_kit.audit_subject_provider`
- **`SeoSiteConfig`** cacheable snapshot DTO (`composeTitle` with `%page%` / `%site%`)

### Notes

- Persistence requires `doctrine/orm` + `doctrine/doctrine-bundle` (Composer `suggest`). Tables: `nowo_seo_site_settings`, `nowo_seo_site_settings_translation`, `nowo_seo_surface`.
- Branch alias `dev-main` → `1.6.x-dev`.
- Additive / opt-in: default `persistence.enabled: false` keeps 1.5.x behaviour.

## [1.5.0] - 2026-09-21

### Added

- **`indexable`** top-level flag: when false, meta defaults to `noindex`, `robots.txt` disallows `/`, and sitemap returns 404/empty
- **`defaults.verification.google` / `bing`** webmaster meta tags
- **Open Graph** `image_width`, `image_height`, `image_alt`, `locale_alternates`
- **`SeoDefaultsProviderInterface`** (`nowo_seo_kit.defaults_provider`) for DB-driven site defaults
- **`SiteIndexabilityProviderInterface`** (`nowo_seo_kit.indexability_provider`) for site-wide indexability
- **`SitemapUrlProviderInterface`** (`nowo_seo_kit.sitemap_url_provider`) for CMS/blog URLs; sitemap XML supports `xhtml:link` alternates
- **Runtime** `title_final`, `alternates`, and pre-encoded `json_ld.json` for host resolvers that already compose the full title / JSON-LD
- **`templates.head_includes_title`** so hosts that own the `<title>` block can omit it from `nowo_seo_head()`
- Sitemap response `Cache-Control: public, max-age=3600`
- Optional CSP `nonce` attribute on JSON-LD `<script>` when `csp_nonce` is passed to the head template
- Composer `branch-alias` `dev-main` → `1.5.x-dev`

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) — host extension points and new keys
- [UPGRADING.md](UPGRADING.md) — upgrade path from 1.4.x

### Notes

- Additive / optional APIs. Existing YAML without the new keys keeps previous behaviour (`indexable: true`, title included in head).

## [1.4.3] - 2026-08-24

### Changed

- **QA:** add `phpstan-frankenphp` extension (REQ-CS-005).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).
- **Dependencies:** routine Composer/npm bumps (Dependabot).

### Notes

- **No API or configuration changes** for integrators unless noted above.

[1.4.3]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.4.3

## [1.4.2] - 2026-08-19

### Security

- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

## [1.4.1] - 2026-08-18

### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).

[1.4.1]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.4.1

## [1.4.0] - 2026-08-04

### Added
- **REQ-TWIG-004:** require `twig/extra-bundle` + `twig/string-extra`; `make check-twig-extra` in `release-check`; demos register `TwigExtraBundle`.
- **Twig-CS-Fixer:** `vincentlanglet/twig-cs-fixer`, `.twig-cs-fixer.php`, `composer twig:lint` / `twig:fix`.

[1.4.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.4.0

## [1.3.1] - 2026-07-30

### Documentation

- USAGE: **Overriding templates (REQ-TWIG-001)** — namespace `@NowoSeoKitBundle`, freeze rule, prefer `templates.head` config over full-file forks; CONFIGURATION points to USAGE.
- README: documentation link order (REQ-DOCS-019); version table includes **1.3.x**.

### Changed

- Dev / CI dependency bumps (Dependabot): php-cs-fixer, Rector, PHPStan group, Symfony PHPUnit bridge, `nowo-tech/phpstan-frankenphp`.
- Demo Symfony 8: php-cs-fixer on config; Flex-style `bundles.php` alignment.

## [1.3.0] - 2026-07-29

### Added

- `SeoPathBuilderInterface` (+ service alias) so other bundles (e.g. RoutingKit) can decorate path resolution for canonical/hreflang.
- FrankenPHP Friendly Worker Mode banner in README (REQ-DOCS-017).
- `make down-dev`; Compose V2→V1 detection (REQ-MAKE-007 / REQ-MAKE-010).
- **REQ-CS-005:** `nowo-tech/phpstan-frankenphp` in `require-dev` with classic + worker rulesets.
- Maintainer scripts / GitHub hygiene: `check-open-prs`, Dependabot, `pr-lint` / `stale` workflows.

### Changed

- Demo Symfony 8: external `docker/entrypoint.sh`; Twig Inspector in demo `require-dev`.

### Documentation

- TOC additions across docs; [UPGRADING.md](UPGRADING.md) **To 1.3.0**; [RELEASE.md](RELEASE.md) updated.

## [1.2.0] - 2026-07-22

### Changed

- **Twig namespace** — templates resolve under `@NowoSeoKitBundle/...` (was `@NowoSeoKit/...`)
- Default `templates.head` is now `@NowoSeoKitBundle/seo/head.html.twig`

### Added

- Application overrides via `templates/bundles/NowoSeoKitBundle/` are prepended so they win over bundle views (REQ-TWIG-002)
- `TwigPathsPass` resolves `twig.loader.native` alias and common filesystem loader service ids

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) and [UPGRADING.md](UPGRADING.md) updated for the Twig namespace rename

## [1.1.0] - 2026-07-18

### Added

- `SeoPathBuilder::resolveCanonicalSlug()` — maps a request slug (canonical or translated) to the configured slug key
- Demo: locale switch (EN / ES / FR) using SEO alternates (locale + localized path / translated slug)
- Demo: optional `_locale` on home (`/` → default locale) via `trailing_slash_on_root: false`
- Demo: SEO Admin CRUD at `/admin/seo` for `pages`, `slug_routes`, and `slugs` (writes `nowo_seo_kit.yaml`)
- Bundle-local `make update-deps` scripts (REQ-MAKE-008): composer update in the bundle **and** demos

### Fixed

- Preserve hyphenated keys under `slugs.*` (e.g. `hello-world`); Symfony Config no longer normalizes them to underscores
- Hreflang / sitemap / metadata for translated slug URLs (e.g. `/es/blog/hola-mundo`) resolve via the canonical slug config

### Documentation

- Demo FrankenPHP docs: root URL, locale switch, SEO Admin
- Upgrading notes for hyphenated slug keys

## [1.0.0] - 2026-07-16

### Added

- First stable release of **SeoKitBundle**
- Hierarchical SEO config: defaults → slug_routes → pages → slugs → `#[Seo]` attribute → `SeoRuntime`
- Twig helpers `nowo_seo_head()` and `nowo_seo_metadata()` (Open Graph, Twitter, JSON-LD, canonical, hreflang)
- Multilingual static routes and translated slug paths
- Route loader type `nowo_seo_kit` registers `/sitemap.xml`, `/robots.txt`, and optional static pages with `controller`
- FrankenPHP demo (Symfony 8)
- PHPUnit unit tests with high line coverage
- GitHub Actions CI, Flex recipe, and documentation pack

[Unreleased]: https://github.com/nowo-tech/SeoKitBundle/compare/v1.10.1...HEAD
[1.10.1]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.10.1
[1.10.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.10.0
[1.9.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.9.0
[1.8.1]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.8.1
[1.8.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.8.0
[1.7.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.7.0
[1.6.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.6.0
[1.5.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.5.0
[1.4.3]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.4.3
[1.4.2]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.4.2
[1.4.1]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.4.1
[1.4.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.4.0
[1.3.1]: https://github.com/nowo-tech/SeoKitBundle/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/nowo-tech/SeoKitBundle/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/nowo-tech/SeoKitBundle/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/nowo-tech/SeoKitBundle/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.0.0
