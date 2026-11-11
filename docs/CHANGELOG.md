# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.24.0] - 2026-11-11](#1240---2026-11-11)
- [[1.23.1] - 2026-11-11](#1231---2026-11-11)
- [[1.23.0] - 2026-11-11](#1230---2026-11-11)
- [[1.22.0] - 2026-11-10](#1220---2026-11-10)
- [[1.21.0] - 2026-11-09](#1210---2026-11-09)
- [[1.20.0] - 2026-11-03](#1200---2026-11-03)
- [[1.19.0] - 2026-10-30](#1190---2026-10-30)
- [[1.18.0] - 2026-10-28](#1180---2026-10-28)
- [[1.17.0] - 2026-10-27](#1170---2026-10-27)
- [[1.16.1] - 2026-10-24](#1161---2026-10-24)
- [[1.16.0] - 2026-10-23](#1160---2026-10-23)
- [[1.15.0] - 2026-10-19](#1150---2026-10-19)
- [[1.14.1] - 2026-10-13](#1141---2026-10-13)
- [[1.14.0] - 2026-10-13](#1140---2026-10-13)
- [[1.13.0] - 2026-10-13](#1130---2026-10-13)
- [[1.12.1] - 2026-10-13](#1121---2026-10-13)
- [[1.12.0] - 2026-10-09](#1120---2026-10-09)
- [[1.11.1] - 2026-10-06](#1111---2026-10-06)
- [[1.11.0] - 2026-10-06](#1110---2026-10-06)
- [[1.10.2] - 2026-09-27](#1102---2026-09-27)
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
- [[1.3.0] - 2026-07-29](#130---2026-07-29)
- [[1.2.0] - 2026-07-22](#120---2026-07-22)
- [[1.1.0] - 2026-07-18](#110---2026-07-18)
- [[1.0.0] - 2026-07-16](#100---2026-07-16)

## [Unreleased]

## [1.24.0] - 2026-11-11

Next minor: 1.24.0. Private entity manager in zero-config mode (REQ-ARCH-001 rule 6, audit MQ-S-M1) and
framework-free twin ports for DDD / hexagonal hosts (rules 1 and 3); no configuration change. See
[UPGRADING](UPGRADING.md#unreleased).

### Changed

- **SeoKit writes no longer flush the application's default entity manager (REQ-ARCH-001 rule 6).** With
  `doctrine.entity_manager` unset, the repositories (admin, JSON API, `SeoSurfaceStoreInterface`,
  `SeoSurfaceManagerInterface`, site settings) now write through `Doctrine\SeoKitEntityManager`, a private manager
  created on the connection, configuration and event manager of the manager that maps the SeoKit entities (resolved
  through `Doctrine\SeoKitManagerRegistry`). Before, a SeoKit save committed every change the application had left
  pending on its default manager, and a failed SeoKit flush reset that manager. Now a SeoKit flush commits only SeoKit
  rows, and a failed one closes only the private manager, which is created again on the next call. The tables, the
  connection and the mapping do not change: no migration, no data move.
  - Dropped at every unit-of-work boundary through `SeoRuntimeClearSubscriber` (same 1.23.1 nested-command and
    in-flight-message guards) and at `kernel.reset` (REQ-RUNTIME-002).
  - With doctrine/dbal 4, `Doctrine\PrivateEntityManagerMiddleware` (on that connection only) reports rolled-back
    transactions and savepoints; the private manager is replaced before its next use, so a save repeated after the
    application rolled back a transaction that included it is written again. A stale manager that still has scheduled
    inserts, updates or deletes is kept, so a pending edit is never dropped (audit MQ-L-L1).
  - The SQL filters enabled on the source manager are mirrored with their parameters on every call; a filter whose
    parameters changed is disabled and enabled again before the copy, so a parameter the source no longer has never
    survives, and a filter the source disabled is disabled (audits MQ-L-L3, MO-Y-D-L1).
  - New option `doctrine.private_entity_manager` (default `true`). `false` restores the previous behaviour and is
    deprecated (removed in 2.0). `doctrine.entity_manager` (dedicated manager) is unchanged.

- `SitemapGenerator` builds URLs through `SeoUrlBuilderInterface::absoluteUrlFor()` when the injected path builder
  implements it (the bundle's `SeoPathBuilder` does); a host builder that implements only `SeoPathBuilderInterface` is
  still called through `absoluteUrl()`. Output is unchanged.
- Docs: `docs/USAGE.md` splits the DDD section into "Standard host" and "DDD host", with the port / deprecated-form
  table.

### Added

- `Model\SitemapContext`: readonly value object with the site origin, the locale and the base path, built from the
  request by `Service\SitemapContextFactory::fromRequest()` or by hand outside HTTP.
- `Service\SitemapEntryProviderInterface::getEntriesFor(SitemapContext)`: framework-free sitemap provider port,
  autoconfigured with the same `nowo_seo_kit.sitemap_url_provider` tag. A provider that implements both interfaces is
  called once, through the new port.
- `Service\SeoUrlBuilderInterface` (`absoluteUrlFor(SitemapContext, string)`, `pagePath`, `resolveCanonicalSlug`,
  `slugPath`): framework-free twin of `SeoPathBuilderInterface`, implemented by `SeoPathBuilder` and aliased to it.
  `base_url` still wins over the context origin.
- `SitemapGenerator::entriesFor(SitemapContext)`: sitemap entries without an HTTP request (console, Messenger,
  application services). Deprecated providers get a synthetic request built from the context.
- `Model\SeoSurfaceData` (readonly DTO, normalised like the entity, `isEmpty()`) and
  `Service\SeoSurfaceStoreInterface` (`findData()`, `save()`, `clear()`), implemented by `SeoSurfaceManager` and
  aliased when `persistence.enabled`. `save()` never writes an all-empty row (it removes the stored one); `save()` and
  `clear()` dispatch `SeoSurfaceChangedEvent` after the write, like `saveOrClear()`.

### Fixed

- Host sitemap providers are now always tagged: `SitemapUrlProviderInterface` relied on `#[AutoconfigureTag]` on the
  interface only, which Symfony reads only when the host loads that interface from its own resources, so an
  autoconfigured host provider could be left out of `/sitemap.xml` unless the host tagged it by hand. Both sitemap
  ports are now registered with `registerForAutoconfiguration()`; a provider tagged twice is called once.

### Deprecated

- `SitemapUrlProviderInterface` (`getEntries(Request)`): implement `SitemapEntryProviderInterface`. Removed in 2.0.
- `SeoPathBuilderInterface` (`absoluteUrl(Request, string)`): use `SeoUrlBuilderInterface`. Removed in 2.0.
- `SeoSurfaceManagerInterface` (entity-typed `find()`, `getOrCreate()`, `saveOrClear()`, `isEmpty()`): use
  `SeoSurfaceStoreInterface` with `SeoSurfaceData`. Removed in 2.0.

### Tests

- `tests/Integration/PrivateEntityManager/PrivateEntityManagerTest.php` (standard host: one default manager with an
  XML-mapped host entity, a tenant SQL filter, no `doctrine.entity_manager`): a pending host insert stays pending and
  unflushed across every write path (store save / update / clear, manager `getOrCreate` / `saveOrClear`, repository
  save / remove, settings `getOrCreate` / `save`); a host transaction rollback and a host savepoint rollback followed by
  the same save write the row (load, edit, savepoint, rollback, save); a stale manager with a pending edit is kept; a
  failed SeoKit flush closes only the private manager, the host manager is neither closed nor reset and the next write
  works; 300 requests through `http_kernel` without the resetter while another worker edits the row read the current
  value, with no memory growth; a nested console command keeps the outer unit's manager; filters mirrored, a parameter
  the source lost is not kept, a disabled filter is disabled. Unit tests for the registry, the middleware, the pass and
  the wiring (including the escape-hatch deprecation).
- `tests/Integration/Ddd/DddHostKernelTest.php`: DDD host kernel (domain object mapped in XML on the host's own
  manager, SeoKit entities on a dedicated manager, a sitemap provider that implements only the context port, no
  `base_url`): `/sitemap.xml` through `http_kernel`, `entriesFor()` without HTTP, and a `SeoSurfaceStoreInterface`
  round trip that leaves a pending host insert unflushed (rule 6).
- Unit tests for `SitemapContext`, `SitemapContextFactory`, `SeoSurfaceData`, `absoluteUrlFor()`, the generator with
  new, deprecated and dual providers and with a deprecated-only path builder, and the store methods.

## [1.23.1] - 2026-11-11

Next patch: 1.23.1.

### Fixed

- **SEO overrides and page head carried over between Messenger messages and console runs (REQ-RUNTIME-002).**
  `SeoRuntimeClearSubscriber` cleared `SeoRuntime`, `PageHeadContext` and the `SeoSiteConfigProvider` memo only at the
  start of a main HTTP request (and `SeoRuntime` at `kernel.terminate`); on the Messenger and console paths only
  `kernel.reset` did it. Where the services resetter does not run between units of work (a host that removes,
  replaces or bypasses `services_resetter` or drives `http_kernel` directly, a consumer started with
  `messenger:consume --no-reset`, or a long-running process that runs several console commands), an e-mail rendered in
  a handler saw the previous message's runtime overrides (title, description, `noindex`, variables) and described page
  head, possibly another tenant's, and kept the site settings read by an earlier message after another worker edited
  them. The subscriber now clears all three at `kernel.terminate`, `WorkerMessageReceivedEvent`,
  `WorkerMessageHandledEvent`, `WorkerMessageFailedEvent`, `console.command` and `console.terminate` as well (Messenger
  and console events subscribed by name; both components stay optional). The bundle wires the services through a new
  `$resettables` iterator of `IGNORE_ON_UNINITIALIZED_REFERENCE` references, so a boundary never builds a service the
  unit of work did not use. The console boundaries clear only when the outermost command starts or ends and no
  Messenger message is in flight, so a command run in-process from another command or from a handler
  (`Application::doRun()`) keeps the outer unit's overrides and page head (audit MI-S-L2). `ResetInterface` /
  `kernel.reset` are kept. Affected: 1.0.0 to 1.23.0, on those paths. Since 1.10.1 the stock `messenger:consume`
  (without `--no-reset`) already reset these services per message; in 1.0.0–1.10.0 `SeoRuntime` was not resettable,
  so stock consumers also carried overrides over (fixed in 1.10.1).

### Tests

- `tests/Integration/Worker/WorkerBoundariesTest.php`: one booted kernel, services resetter never called; 500 HTTP
  requests to two pages through `http_kernel`, 500 messages through a real Messenger `Worker` without
  `ResetServicesListener` (every 7th fails) and 500 console `Application` runs, alternating two tenants, with a site
  rename by "another worker" before each unit; asserts each unit starts with no overrides, no described head and the
  current site name, and memory grows less than 1 MiB after a 50-unit warm-up. Nested `doRun()` tests (from a
  command and from a Messenger handler) keep the outer unit's state. Direct `clearState()` test.
- `symfony/messenger` added to `require-dev` (tests only) and to the CI matrix install.

### Changed

- FrankenPHP worker audit (igor): `SeoRuntime::reset()` empties the overrides and variables directly; the igor warning was a false positive (they were already cleared through `clear()` and at every main request / `kernel.terminate`). See `docs/FRANKENPHP-WORKER-AUDIT.md`.
- CI: new standalone `igor` job runs `composer igor` (REQ-CS-008).

## [1.23.0] - 2026-11-11

New doctrine.entity_manager maps SeoKit entities on a host-declared dedicated manager only; the host default entity manager stays the default; recovery never touches host managers. See [UPGRADING](https://github.com/nowo-tech/SeoKitBundle/blob/v1.23.0/docs/UPGRADING.md#to-1230).

### Added

- `nowo_seo_kit.doctrine.entity_manager` (default `null`): maps the SeoKit entities on that entity manager only, so a
  DDD host can keep them off its own manager. The repositories then write to, and reset after a failed flush, only that
  manager (rule 6 of the bundle architecture bar). Also:
  - the entities are removed from every other manager, including one with `auto_mapping` (audit LD-SEO-L1);
  - the host's default manager stays the default even when the host does not set `default_entity_manager`, including
    the Flex shorthand (audit LD-SEO-M1);
  - the build fails when the host's `doctrine.orm.entity_managers` does not declare the manager, so a mistyped name
    no longer creates a new manager on the default connection (audit LD-SEO-L2).

  `null` keeps the mapping on the default manager.

### Changed

- Tests prove that a failed SeoKit flush resets only the manager of the SeoKit entities and never a closed host manager,
  including in a kept-alive kernel without `kernel.reset` (FrankenPHP worker mode, `FRANKENPHP_RESET_KERNEL=false`).

## [1.22.0] - 2026-11-10

Target **1.22.0** (minor, additive).

### Added

- `Service\SeoSurfaceManagerInterface`, implemented by `SeoSurfaceManager` (stays `final`) and aliased when
  `persistence.enabled`.
- Id-only change events dispatched after the flush: `Event\SeoSurfaceChangedEvent` (surface key, locale, row id,
  `deleted`, actor id, occurred-at) from the surfaces admin, the JSON surface API and
  `SeoSurfaceManager::saveOrClear()`; `Event\SeoSiteSettingsChangedEvent` (actor id, occurred-at) from the settings
  admin, after `SeoSiteConfigProviderInterface::refresh()`. New `Service\SeoChangeNotifier` (registered with
  persistence) dispatches them; the three admin/API controllers and `SeoSurfaceManager` take it as an optional last
  argument.

### Documentation

- USAGE: new section "Using it from a DDD / hexagonal application" (where the bundle sits in a hexagonal host, the ports, events and DTOs to build an anti-corruption layer on, and the known limitations).

## [1.21.0] - 2026-11-09

### Added

- **Brand from `nowo-tech/brand-contracts` (spec 220 S6).** New `nowo_seo_kit.brand` option (`auto`, the default, or
  `off`; `"auto"` / `"off"` is accepted as a shorthand). When the application has a
  `Nowo\Brand\Contract\BrandProviderInterface` service (for example InstanceSettingsBundle 1.5 with
  `brand.enabled: true`), the brand name fills `defaults.site_name` when it is empty after the configuration and the
  defaults providers, so `og:site_name`, the `{site_name}` title placeholder and the PageHead site name follow it, and
  fills the `name` of a configured JSON-LD Organization that has none. Configured values always win.
- `brand.images` (default `false`, opt-in): the brand logo also becomes the fallback `og:image` / `twitter:image`
  (`logo_light`, then `mark`, with the logo's own width and height) and the JSON-LD Organization `logo` (`mark`, then
  `logo_light`) when nothing is configured. Only PNG, JPEG and WebP assets are used (never SVG or ICO), and the URL is
  made absolute on `base_url` (else the request origin) and checked to be a plain http(s) URL.
- `nowo-tech/brand-contracts` `^1.0` in `require-dev` and `suggest` (with `conflict: <1.0 || >=2.0`); it is not a
  runtime requirement.

### Unchanged

- Without a brand provider, with `brand: off`, or with a provider that throws (logged as a warning), the head, the
  JSON-LD and the PageHead defaults are byte-identical to 1.20.0.

## [1.20.0] - 2026-11-03

replaceable table-name prefix (doctrine.table_name_prefix), stricter prefix validation

### Added

- `doctrine.table_name_prefix` (default `nowo_seo_`) replaces the `nowo_seo_` part of every table and named
  unique-constraint name (`seo_` gives `seo_surface`, `seo_site_settings`, `seo_site_settings_translation`,
  `uniq_seo_surface_key_locale`), so a host can name the tables after its own conventions. With the default every
  table, index, unique and foreign-key name is byte-identical (golden schema test). `doctrine.table_prefix` keeps
  prepending to the result. Rename SQL for MySQL / MariaDB and PostgreSQL in [UPGRADING](UPGRADING.md#unreleased).

### Changed

- `doctrine.table_prefix` / `doctrine.table_name_prefix` must match `/^[a-z][a-z0-9_]*\z/` (uppercase and a leading
  digit are now rejected) and may not make a generated name longer than 63 characters; checked at compile time;
  `%env()%` is rejected for both options (table names are part of the schema).

## [1.19.0] - 2026-10-30

UiKit page contract, access_roles/access_checker (allow_unauthenticated debug-only), opt-in routes, audit BV hardening (literal roles, legacy-key conflicts, placeholder allowlist, filtered area nav). See [UPGRADING](UPGRADING.md#to-1190).

Admin UI on the UiKit page contract with the standard `web_ui` / `security` configuration, plus security hardening.
See [UPGRADING](UPGRADING.md#unreleased).

### Added

- **Admin UI on the UiKit page contract (REQ-UI-001, REQ-UI-003).** The settings page and the surface form extend the
  UiKit form scaffold, the surfaces list the UiKit list scaffold; `Ui\SeoKitPages` builds the view models
  (`FormPage`, `ListPage`, header tabs, `Column`, row action `edit`, `EmptyState`), so layout, CSS stack
  (`nowo_ui_kit.css_framework`: bootstrap5 / tailwind / foundation / custom), icons, row-action display and tokens come
  from UiKit — no page to copy. Locale switch of the surfaces list = a GET filter form
  (`Form\SeoSurfaceLocaleFilterType`); audit problems render as translated badges.
- **`web_ui` configuration:** `enabled`, `settings`, `surfaces`, `path_prefix` (null keeps the 1.x paths; e.g.
  `/admin/seo` → `/admin/seo/settings`, `/admin/seo/surfaces…`), `layout_template` (null = UiKit admin layout),
  `templates.{settings,surfaces_index,surfaces_form}`, `form_themes` (default the UiKit form layout), `api_enabled`,
  `api_csrf_protection`.
- **`security` configuration (REQ-UI-002):** `access_roles` (default `[ROLE_ADMIN]`, any role grants),
  `access_checker` (host service implementing `Security\SeoKitAccessCheckerInterface`), `allow_unauthenticated`
  (demo only). Every admin and API action goes through the checker.
- **Area navigation** `seo_kit` (`Ui\SeoKitAreaNav`, UiKit area-nav registry: `nowo_ui_area_nav('seo_kit')`), also
  rendered as page-header tabs.
- UiKit `PageContractTest` (strict missing-message rule) and a functional kernel test (Security + Doctrine SQLite +
  UiKit: anonymous 401, no role 403, admin 200 under `path_prefix`). `docs/TEMPLATES.md` documents pages, view models
  and data blocks.

### Changed

- `nowo-tech/ui-kit-bundle` `^2.17`; `symfony/security-csrf` and `symfony/security-core` are runtime requirements.
- The seeded FormKit profile `seo_kit` has no Bootstrap classes any more (the UiKit form theme applies the theme slots)
  and sets `convention_requires_translation`: auto help / placeholder keys only when translated.
- The localised settings fields share one label per field with `%locale%` (`nowo_seo_site.default_title.label`, …):
  every enabled locale gets a translated label (1.x only had `_en` / `_es` keys and showed raw keys for others).
- Admin controllers take `SeoKitAccessCheckerInterface` + `SeoKitPages` instead of `$role` / template strings.
- Surfaces list edit links encode the surface key once (1.x links, encoded twice, still work).

### Deprecated

- The `admin.*` keys (mapped onto `web_ui.*` / `security.*`; removed in 2.0): `admin.enabled`, `admin.settings`,
  `admin.surfaces`, `admin.role`, `admin.settings_template`, `admin.surfaces_index_template`,
  `admin.surfaces_form_template`, `admin.api_enabled`, `admin.api_csrf_protection`.

### Removed

- `admin/_flashes.html.twig` (UiKit `flashes` partial of the layout) and the standalone `<!DOCTYPE html>` admin shells.

### Security

- **Independent SEC-004 review (Appendix BV, Pass good / Low) fixes:**
  - deprecated `admin.role` and `security.access_roles` refuse `%env()%` placeholders (a runtime `PUBLIC_ACCESS`
    could pass the build-time check);
  - a deprecated `admin.*` key and its replacement set to different values is a configuration error (a leftover
    `admin.api_csrf_protection: false` / `admin.role` no longer silently weakens the migrated value);
  - title / description placeholders: route parameters only (allowlist; `_*` and secret-looking names dropped), so
    `{api_key}` / `{_firewall_context}` no longer print request attributes;
  - a settings POST with missing fields is a validation error instead of a 500;
  - `web_ui.path_prefix` refuses `.` / `..` segments;
  - the `seo_kit` area nav returns nothing for users the access checker refuses;
  - `OriginUrlGuard` accepts its own URLs when the origin has a trailing slash (`DEFAULT_URI` resolved at runtime);
  - `.github/SECURITY.md` lists 1.19.x; `docs/SECURITY.md` records the grade, residuals and the "every account" roles.

- **Admin access fails closed (REQ-UI-002):** an empty `security.access_roles` is refused while `web_ui.enabled`
  unless `security.access_checker` is set; `PUBLIC_ACCESS` / `IS_AUTHENTICATED_ANONYMOUSLY` (also as `admin.role`)
  are refused; without SecurityBundle the container does not compile unless `allow_unauthenticated` is true
  (`WebUiSecurityPass`). `security.allow_unauthenticated: true` is refused unless `kernel.debug` is true, and always
  in the `prod` environment; `allow_unauthenticated`, `web_ui.enabled` and `web_ui.api_csrf_protection` must be
  literal booleans (no `%env()%` placeholder).
- **URL hardening:** canonical overrides and image URLs with inner control characters or whitespace (`/\t/evil`) are
  rejected; the default Open Graph image and the organisation logo of the site settings must be a site path or an
  https URL.
- **Admin routes only through the opt-in loader:** the admin / API controllers are no longer autoconfigured, so on
  Symfony >= 7.3 the host's default `routing.controllers` import does not load their `#[Route]`s behind the bundle's
  back (which bypassed `web_ui.enabled` / `web_ui.path_prefix` and duplicated the route names).
- **JSON API:** locales that are not enabled and surface keys over 180 characters answer 404 (no 500).
- **Title placeholders:** request attributes named like nonces, tokens, CSRF values, secrets or passwords are no longer
  offered as `{placeholder}` values.

## [1.18.0] - 2026-10-28

Security: admin CSRF fails closed and the recipe sets base_url from DEFAULT_URI. See [UPGRADING](UPGRADING.md#to-1180).

### Security

- **Admin CSRF fails closed.** With `admin.enabled: true` the container refuses to compile without
  `security.csrf.token_manager` + `form.type_extension.csrf`, or with `framework.form.csrf_protection: false`
  (`CsrfProtectionPass`): the settings / surface forms would otherwise accept cross-site posts.
- **Recipe sets `base_url: '%env(default::DEFAULT_URI)%'`.** Without `base_url`, canonical, og:url, hreflang, sitemap
  `<loc>` and the robots `Sitemap:` line follow the request `Host` header (cache / canonical poisoning without
  `trusted_hosts`); existing hosts should set it — see [SECURITY](SECURITY.md). Docs corrected (the absolute URLs do not
  go through `OriginUrlGuard`); `.github/SECURITY.md` lists 1.17.x.

## [1.17.0] - 2026-10-27

### Added

- `x_robots_tag.enabled` (default `false`): sends the page's resolved robots value (defaults → slug routes → pages →
  slugs → attribute → runtime; `indexable: false` forces noindex) as an `X-Robots-Tag` header on every main response —
  HTML, JSON or files, with or without `nowo_seo_head()` in the layout (`EventSubscriber\XRobotsTagSubscriber`). A
  response that already carries the header keeps it. Typical use: a private app with `defaults.robots:
  'noindex,nofollow'` and an indexable sign-in page (`pages.<login route>.robots: 'index,follow'`).

### Changed

- Demo: the SEO admin CRUD forms (`admin/seo/page_form`, `slug_form`, `slug_route_form`) guard their child loop with `not child.rendered` (the CSRF token now renders through the loop instead of being skipped by name), and the CSRF-only delete forms (`pages`, `slugs`, `slug_routes`) render their token through the same loop; same URLs, field names and confirm prompts (REQ-TWIG-003 / REQ-TWIG-005).
- Docs: USAGE states the Symfony Form / child-loop rule for admin and demo forms and that host overrides and demos must not reintroduce raw `<form>` / `<input>` (REQ-TWIG-005 rule 9).

## [1.16.1] - 2026-10-24

Bundle views render form bodies through the child loop (REQ-TWIG-003). See [UPGRADING](UPGRADING.md#to-1161).

### Changed

- Admin views `admin/settings.html.twig` and `admin/surfaces_form.html.twig` render the form body through the child loop
  (`{% for child in form %}` + `not child.rendered` + `form_row(child)`) instead of one `form_row(form.<field>)` per field
  (REQ-TWIG-003). Same fields, same order; the settings page still emits the per-locale wording tabs and the organisation
  heading right before `organisationLegalName`. Fields added to `SeoSiteSettingsType` / `SeoSurfaceType` now appear
  without template overrides.

[1.16.1]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.16.1

## [1.16.0] - 2026-10-23

REQ wave 2: CSRF header on the JSON surface API, table prefix, backed enums, kernel tests, demo Symfony Forms and release gates. See [UPGRADING](UPGRADING.md#to-1160).

### Added

- `doctrine.table_prefix` (default `''`): `TablePrefixListener` prefixes the SeoKit tables and their named unique constraints when persistence is enabled (REQ-ORM-002).
- `admin.api_csrf_protection` (default `true`) and backed enums `SitemapChangeFreq` / `TwitterCard` for the `sitemap_changefreq` and `defaults.twitter.card` value sets (REQ-PHP-001).
- Kernel integration tests under `tests/Integration/` (sitemap.xml, robots.txt through the HTTP kernel) (REQ-TEST-002).
- `make check-open-prs`, `make check-no-raw-html-form`, `make demo-smoke` and the `Demo smoke` workflow; demo `restart`, `test` and `verify` targets (REQ-MAKE-002/003, REQ-TEST-011).
- `docs/SECURITY.md`: attack surface / threat model, CSRF and logging sections; `.github/SECURITY.md` names the package and lists current versions (REQ-SEC-001/003, REQ-OBS-001).
- Cursor rule `20-twig-and-public-assets.mdc` (REQ-IDE-003).

### Security

- The JSON surface API (`POST /_nowo/seo/surfaces/{key}/{locale}`) requires an `X-CSRF-Token` header (token returned as `csrf_token` by `GET`); fail-closed without a CSRF manager (REQ-SEC-005). See [UPGRADING](UPGRADING.md#json-surface-api-needs-an-x-csrf-token-header).

### Changed

- Demo admin pages use Symfony Forms (`form_start` / `form_end`, CSRF-protected POST deletes) instead of raw HTML forms (REQ-DEMO-001); Symfony 8 demo runs on PHP 8.5 (REQ-DEMO-010); DNS settings documented (REQ-DEMO-009).
- `release-check` adds open-PR, raw-form, translation-parity and demo-smoke gates; demo healthcheck failures now fail the release; `test-coverage` keeps PHPUnit's exit code through `tee`.
- `validate-translations` checks key parity across locales (REQ-MAKE-004).
- CI: PHPStan, raw-form gate and translation parity run on every push and PR (REQ-CS-006); tests run with `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` (REQ-SF-005).
- `.scripts/check-no-cursor-coauthor.sh` accepts git worktrees.

- Dependencies refreshed (Composer and pnpm/npm lock files of the bundle and its demos, campaign 2026-10-19).
- `.php-cs-fixer.dist.php`: `new ParallelConfig(2)` (REQ-CS-001).
- Documentation: wave-1 REQ compliance — README GitHub stars badge, section order (Version information and Demos before Documentation) and documentation list order, canonical issue templates, CONTRIBUTING Cursor Agent settings (REQ-IDE-006), tables of contents, SPEC-DRIVEN-DEVELOPMENT rewritten to the REQ-DOCS-013 pattern (user stories, scope/non-goals, REQ table, Engram relationship), full GitHub Spec Kit manual, Spec Kit infrastructure (`.specify/`) initialized for Cursor Agent, baseline spec success criteria and validation commands, inventory 88/88 with coverage summary.

[1.16.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.16.0

## [1.15.0] - 2026-10-19

Locale tabs for the per-locale wording on /settings/seo (FormKit 2.13 fields mode, UiKit 2.5 renderer). See [UPGRADING](UPGRADING.md#to-1150).

### Changed

- **`/settings/seo` per-locale wording as locale tabs**: `defaultTitle_<locale>`, `homeTitle_<locale>`,
  `defaultDescription_<locale>` and `organisationDescription_<locale>` render as one tab per enabled locale through
  the FormKit `locale_tabs` include (`fields` mode), shown with the UiKit `locale_tabs` partial when UiKit is
  registered: default locale first and marked, the first tab with errors opened, keyboard navigation, every locale
  visible as a stacked fieldset without JavaScript. The settings page loads `nowo-ui.css` and `nowo-ui-tabs.js` when
  UiKit is registered. Field names, the form data and the stored translations are unchanged; translations of locales
  that are not enabled stay untouched on save.
- Requires `nowo-tech/form-kit-bundle` **^2.13** (was ^2.4) and `nowo-tech/ui-kit-bundle` **^2.5.1** (new).
- The surfaces pages (one record per locale) keep their link navigation.

[1.15.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.15.0

## [1.14.1] - 2026-10-13

### Security

- Raw `json_ld.json` strings now have every `<` / `>` encoded as `\u003C` / `\u003E` (same as `JSON_HEX_TAG` for `document` / `graph`). 1.13.0 only escaped `</`, so a `<!--<script>` sequence could still put the HTML parser into the script-data escaped state and swallow the rest of the head. The JSON value is unchanged for consumers.

### Fixed

- `composer.json` branch alias `dev-main` moved from `1.12.x-dev` to `1.14.x-dev`.
- `routes.yaml`, `routes_admin.yaml` and USAGE.md said the legacy route files were deprecated "since 1.13.1"; the deprecation shipped in 1.14.0.

[1.14.1]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.14.1

## [1.14.0] - 2026-10-13

### Fixed

- `nowo_seo_head()` now actually emits the announced CSP `nonce` on the JSON-LD `<script>`: it accepts an optional nonce argument (`{{ nowo_seo_head(nonce) }}`) and otherwise reads the request attribute `csp_nonce` (it was hard-coded to `null`).
- German, French, Italian, Dutch and Portuguese catalogues now translate all admin, form and validation keys (73 keys were missing and fell back to English); a key-parity test guards every locale against `en`.

### Deprecated

- `@NowoSeoKitBundle/Resources/config/routes.yaml` and `routes_admin.yaml` (not used by the Flex recipe or docs): they ignore the `sitemap.*` / `robots.*` path and enable toggles and the `admin.*` toggles. Import `type: nowo_seo_kit` / `type: nowo_seo_kit_admin` instead; the files will be removed in 2.0.

### Changed

- Docs: `page_head.open_graph_regions` documented in CONFIGURATION.md, including that PageHead (and therefore `page_head.*`) is only registered when `base_url` is set, and that `persistence` / `admin` should be used with `base_url`.

[1.14.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.14.0

## [1.13.0] - 2026-10-13

### Security

- Surface SEO JSON API (`POST /_nowo/seo/surfaces/{key}/{locale}`) now requires `Content-Type: application/json` (415 otherwise; 400 for a non-object body). Form-encoded cross-site posts are rejected, and JSON requests no longer fail with 422 when CSRF protection is enabled.
- Raw `json_ld.json` strings are emitted with `</` escaped as `<\/`, so they cannot close the `<script>` element.
- Sitemap XML escapes quotes in attribute values (`ENT_QUOTES`).

### Fixed

- hreflang alternates are no longer generated as `/page?_locale=xx` for routes without a `{_locale}` path variable (or localized route variants); such locales are skipped.
- `indexable: false` (config or a defaults provider) now forces `noindex,nofollow` even when a page, slug or provider sets `robots: 'index, follow'`.
- Opening the surface admin edit form (GET) no longer creates an empty `SeoSurface` row.
- Sitemap no longer lists pages / slugs marked `noindex`.
- Admin templates (settings, surfaces list and form) now render flash messages.

### Changed

- Symfony requirement floors aligned to `^7.4 || ^8.0` for every component (Symfony 7.0–7.3 were already not installable because of the form/validator/translation floors). CI now covers PHP 8.4 / 8.5 and Symfony 8.0 / 8.1; branch alias `1.12.x-dev`.

[1.13.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.13.0

## [1.12.1] - 2026-10-13

### Changed

- Development: `composer.json` pins `config.platform.php` to 8.2.0 so the committed lock stays installable on the minimum PHP; CI overrides the platform per matrix cell.
- Repository: `.twig-cs-fixer.cache` is now git-ignored.

### Dependencies

- `doctrine/orm` 3.7.4 → 3.7.5 (development lock; #41).
- `nowo-tech/form-kit-bundle` 2.6.1 → 2.7.1 (development lock; #42).

[1.12.1]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.12.1

## [1.12.0] - 2026-10-09

### Added

- Social profiles accept `none` (`SeoSiteConfig::NO_SOCIAL_PROFILES`): an explicit "no social profiles", distinct from an empty list (hosts that ship default profiles can tell them apart via `SeoSiteConfig::hasNoSocialProfiles()`). `SeoSiteConfig::socialProfileUrls()` returns only http(s) URLs; `sameAs` output uses it. Admin help text (en/es) documents the keyword.

### Security

- `seo/head.html.twig` JSON-LD fallbacks (`jsonLd.document` / `jsonLd.graph`) encode with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` like `StructuredDataGraph::toJson()`, so editor text cannot break out of the `<script>` block. Covered by `HeadTemplateJsonLdTest`.

### Fixed

- **Demo:** `demo/symfony8` lock now installs `nowo-tech/form-kit-bundle` (already registered in `bundles.php`) and requires `symfony/asset` (FormKit prepends a `framework.assets` package).

### Dependencies

- Lock refreshed: `nowo-tech/form-kit-bundle` 2.6.1, PHPStan 2.3.1, `phpstan/phpstan-phpunit` 2.1.1, `nowo-tech/phpstan-frankenphp` 1.2.3.
- Demo `symfony8`: Symfony 8.1.8, Twig 3.30.0.

[1.12.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.12.0

## [1.11.1] - 2026-10-06

### Added

- **`ConfigPageHeadDefaultsProvider`** — YAML `defaults` as PageHead fallback when `base_url` is set (hosts may still alias `PageHeadDefaultsProviderInterface`).
- Composer **suggest** `nowo-tech/generative-seo-kit-bundle` for GEO (not geolocation).

### Changed

- Docs and spec (FR-SEO-008 / FR-SEO-010): AI crawler policy and `/llms.txt` belong in GenerativeSeoKit; do not duplicate `robots.groups` User-agents.

[1.11.1]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.11.1

## [1.11.0] - 2026-10-06

### Added

- **`nowo_seo_kit.robots.groups`** extra User-agent blocks (AI crawlers, etc.) when the site is indexable.
- **`GeoRobotsGroupsProviderInterface`** (`nowo_seo_kit.geo_robots_groups_provider`) for host-driven groups.
- JSON-LD nodes: `LocalBusinessNode`, `PersonNode`, `FaqPageNode`, `BlogPostingNode`.

### Changed

- **require-dev:** `igor-php/igor-php` `^0.10.1` (single-file / editor audit and wider process-state detection). Consumers do not pull Igor transitively.

### Notes

- Branch alias `dev-main` → `1.11.x-dev`.
- Specs: FR-SEO-008 / FR-SEO-009 / FR-SEO-010; `code-inventory.md` refreshed for current `src/`.

[1.11.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.11.0

## [1.10.2] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[1.10.2]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.10.2

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

[Unreleased]: https://github.com/nowo-tech/SeoKitBundle/compare/v1.24.0...HEAD
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
