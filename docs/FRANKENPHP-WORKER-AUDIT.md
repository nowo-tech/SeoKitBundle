# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/seo-kit-bundle` (`symfony-bundle`) |
| Audited revision | `v1.10.1` |
| Audit date | 2026-09-25 |
| Method | Manual review of every file under `src/` (services, persistence providers, repositories, Twig extension, controllers, event subscriber, route loaders, form types, audit command), `src/Resources/config/*.yaml`, the DI extension and compiler pass |
| **Verdict** | ✅ **Viable under scenario B** — `SeoRuntimeClearSubscriber` clears `SeoRuntime`, `PageHeadContext` and the site settings memo at the start of every main request; repository flushes recover a closed EntityManager. Clearing the application's EntityManager identity map between requests remains the application's responsibility |
| Original verdict | ⚠️ Viable with conditions — safe only when `services_resetter` runs (scenario A) |
| Remediation | W-01…W-04 resolved in **v1.10.1**: request-start reset in `SeoRuntimeClearSubscriber` (`kernel.request` 4096, main only), `SeoRuntime implements ResetInterface`, `ResetsClosedEntityManagerTrait` + unique-violation recovery in both repositories, refresh of the managed settings row. Regression tests simulate consecutive requests on the same instances without `reset()` |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `PageHeadContext` (`$input`, `$resolved`), `SeoSiteConfigProvider` (`$config`), `SeoRuntime` (`$overrides`, `$variables`) are cleared at the start of every main request by the bundle (W-01, W-02, W-04); everything else is `readonly` or has no properties |
| Static properties / `static` locals | ✅ | None |
| `ResetInterface` / `kernel.reset` coverage | ✅ | `PageHeadContext`, `SeoSiteConfigProvider` and now `SeoRuntime` implement `ResetInterface` (scenario A); the bundle does not depend on it (scenario B) |
| Request / user / locale captured in services | ✅ | `RequestStack` is read at call time in `SeoMetadataResolver::resolve()`; nothing is captured in constructors |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used; configuration is compiled into container parameters |
| Doctrine / EntityManager | ✅ (bundle side) | Repository flushes reset a closed manager before rethrowing; `getOrCreate()` races return the concurrently created row; the managed settings row is refreshed before being snapshotted (W-03). Identity map clearing stays with the application under B |
| Output, headers, `exit`, shutdown functions | ✅ | None; controllers return `Response` objects |
| Resources (files, sockets, cURL) held open | ✅ | None |
| Memory growth across requests | ✅ | No accumulating arrays; `SeoRuntime` uses `array_replace` but is cleared at request start and on terminate |
| Blocking I/O and timeouts | ✅ | No outbound I/O in the bundle; only DB (persistence) and the PSR-6 `cache.app` pool |
| Third-party static state | ✅ | FormKit `FormKitAbstractType` / `withBuilder()` restores the bound builder in `finally` (vendor) |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist`. `composer phpstan` reports 1 pre-existing error (`SeoKitExtension::registerAudit()` iterable type), unrelated to this remediation |

Worker demo: `demo/symfony8/docker/frankenphp/Caddyfile` has the `worker` line commented out (`# worker /app/public/index.php`), so the demo runs in classic mode.

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `Service\PageHeadContext` (only when `base_url` is set) | yes | `$input`, `$resolved`; `kernel.reset` + cleared on main `kernel.request` | ✅ | ✅ |
| `Service\Persistence\SeoSiteConfigProvider` (persistence enabled) | yes | `$config` (request memo); `kernel.reset` + cleared on main `kernel.request`; cross-worker freshness via `cache.app` | ✅ | ✅ |
| `Service\SeoRuntime` | yes (`shared: true`) | `$overrides`, `$variables`; `ResetInterface` + cleared on main `kernel.request` and `kernel.terminate` | ✅ | ✅ |
| `EventSubscriber\SeoRuntimeClearSubscriber` (`kernel.request` 4096, `kernel.terminate`) | yes | none (`final readonly`) | ✅ | ✅ |
| `Service\SeoMetadataResolver` | yes | none (`final readonly`) | ✅ | ✅ |
| `Service\PageHeadResolver`, `HreflangSetBuilder`, `AbsoluteUrlBuilder`, `PageHeadRuntimeBridge` | yes | none (`final readonly`) | ✅ | ✅ |
| `Service\SeoPathBuilder`, `SeoTemplateRenderer`, `OriginUrlGuard`, `SeoPencilCatalogFactory`, `StructuredData\SiteStructuredDataFactory` | yes | none | ✅ | ✅ |
| `Service\SitemapGenerator`, `RobotsTxtGenerator` | yes | none (`final readonly`) | ✅ | ✅ |
| `Service\Persistence\DoctrineSeoDefaultsProvider` | yes | none (`final readonly`); reads `SeoSiteConfigProvider` | ✅ | ✅ |
| `Service\SeoSurfaceManager` | yes | none (`final readonly`) | ✅ | ✅ |
| `Service\Audit\SeoAuditor`, `SeoAuditRules` | yes | none | ✅ | ✅ |
| `Repository\SeoSiteSettingsRepository`, `SeoSurfaceRepository` | yes | none (only `readonly` fallback strings and the registry) | ✅ | ✅ |
| `Twig\SeoKitExtension` | yes | none (`readonly` promoted properties) | ✅ | ✅ |
| `Controller\SeoKitController`, `Admin\SeoSiteSettingsController`, `Admin\SeoSurfaceController`, `Api\SeoSurfaceApiController` | yes | none (`readonly` promoted properties) | ✅ | ✅ |
| `Routing\SeoStaticRouteLoader`, `SeoAdminRouteLoader` | yes | `$loaded` flag (route build time only, see Info) | ✅ | ✅ |
| `Form\SeoSiteSettingsType`, `SeoSurfaceType` | yes | only `readonly` dependencies and FormKit trait fields | ✅ | ✅ |
| `Command\SeoAuditCommand` | CLI only | n/a | n/a | n/a |

Models (`SeoMetadata`, `PageHead`, `PageHeadInput`, `SeoSiteConfig`, structured-data nodes) are created per call and are never stored in a service, except the `SeoSiteConfig` snapshot in `SeoSiteConfigProvider` (site-wide data, not user data) and the `PageHeadInput` / `PageHead` in `PageHeadContext`.

## Findings

### W-01 — `PageHeadContext` keeps the previous page head without a reset (Medium)

- **Where:** `src/Service/PageHeadContext.php:15-16` (`$input`, `$resolved`), `:22-26` (`describe()`), `:28-31` (`isDescribed()`), `:33-40` (`pageHead()` memoizes the resolved head), `:43-47` (`reset()`). Registered with an explicit `kernel.reset` tag in `src/DependencyInjection/SeoKitExtension.php:284-289`.
- **Worker impact:** the host describes the current page from a controller, and the layout usually checks `isDescribed()` before rendering `pageHead()`.
  - Scenario A: safe; `reset()` clears both properties after every request.
  - Scenario B: a request whose controller does not call `describe()` (error pages, redirects rendered with a layout, pages that use the older `SeoRuntime` path) sees `isDescribed() === true` and renders the title, description, canonical, hreflang and JSON-LD of the page served before, which may have been rendered for another user. This is a cross-request leak limited to scenario B.
- **Recommendation:** keep `services_resetter` enabled. A B-safe variant would bind the input to the current main `Request` (for example store it in a request attribute) instead of a service property.
- **Status:** Resolved — `src/EventSubscriber/SeoRuntimeClearSubscriber.php` now subscribes to `kernel.request` (priority 4096, main requests only) and calls `PageHeadContext::reset()` (optional `?PageHeadContext` argument, wired with `@?`), so every request starts undescribed before any controller runs; sub-requests keep their main request's head. The `kernel.reset` tag is kept for scenario A. Test: `SeoRuntimeClearSubscriberTest::testConsecutiveMainRequestsDoNotLeakStateWithoutKernelReset`, `testSubRequestsKeepTheStateOfTheirMainRequest`.

### W-02 — Site settings are memoized per worker (Medium)

- **Where:** `src/Service/Persistence/SeoSiteConfigProvider.php:26` (`$config`), `:38-68` (`get()` returns the memo first, then `cache.app`, then the DB), `:70-75` (`refresh()`), `:77-81` (`reset()`). Registered with `setAutoconfigured(true)` in `src/DependencyInjection/SeoKitExtension.php:229-237`, so the `kernel.reset` tag comes from `ResetInterface` autoconfiguration.
- **Worker impact:** `refresh()` is only called by the worker that saves the settings (`src/Controller/Admin/SeoSiteSettingsController.php:51`); it deletes the shared cache item, so other workers read fresh data on their next `get()` only if their memo is empty.
  - Scenario A: safe; the memo is cleared after every request and the next request reads the shared cache.
  - Scenario B: every other worker keeps the first snapshot it loaded until it restarts: site name, robots / `indexable`, verification codes, organisation JSON-LD and per-locale titles stay stale. This affects `DoctrineSeoDefaultsProvider`, `SitemapGenerator::isIndexable()`, `RobotsTxtGenerator` and `SeoAuditor`. The data is site-wide, so there is no cross-user leak, but a site switched to `noindex` can keep serving `index, follow` (or the reverse).
- **Recommendation:** rely on `kernel.reset`. For B-safety, drop the in-memory memo (the PSR-6 cache already avoids the DB query) or store a version stamp in the pool and compare it on each request.
- **Status:** Resolved — the memo is now request-scoped: `SeoRuntimeClearSubscriber` calls `SeoSiteConfigProvider::reset()` at the start of every main request, so each request reads the shared `cache.app` item that `refresh()` deletes on save (cross-worker invalidation goes through the shared pool). To avoid a worker with a stale identity map re-populating the pool with old data, `SeoSiteSettingsRepository::getOrCreate()` refreshes the managed row (`translations` now cascade `refresh`). Test: `SeoRuntimeClearSubscriberTest::testConsecutiveMainRequestsDoNotLeakStateWithoutKernelReset`, `SeoRepositoriesTest::testSiteSettingsGetOrCreateRefreshesAlreadyManagedRow`.

### W-03 — Failed `flush()` on the read path is swallowed and leaves a closed EntityManager (Medium, scenario B only)

- **Where:** `src/Repository/SeoSiteSettingsRepository.php:28-47` (`getOrCreate()` persists and flushes the singleton row when it is missing), called from `SeoSiteConfigProvider::get()` (`src/Service/Persistence/SeoSiteConfigProvider.php:50-57`), which catches every `Throwable` and falls back to defaults (`:59-65`). Other flushes: `src/Repository/SeoSiteSettingsRepository.php:53`, `src/Repository/SeoSurfaceRepository.php:41`, `:49`, `:55`.
- **Worker impact:** on a fresh install, two workers rendering their first page at the same time can both try to insert the singleton row; the loser gets a unique-constraint error, Doctrine closes the EntityManager, and the provider hides the error behind the fallback. The rest of that request cannot use Doctrine (this also happens in classic mode). Scenario A: DoctrineBundle's `kernel.reset` hook resets the closed manager before the next request. Scenario B: nothing reopens it, so every later request in that worker fails on any Doctrine call. The same cascade follows any failed admin `flush()`.
- **Recommendation:** run with the resetter (scenario A). Creating the singleton row in a migration or install command, instead of on the page-render path, removes the race.
- **Status:** Resolved (bundle side) — new internal `src/Repository/ResetsClosedEntityManagerTrait.php`: every repository flush (`getOrCreate()`, `save()`, `remove()` in both repositories) resets the manager through `ManagerRegistry::resetManager()` when the failure closed it, then rethrows. `getOrCreate()` catches `UniqueConstraintViolationException` and returns the row created by the concurrent request (rethrows if it is still missing). The bundle never calls `clear()` on the application's EntityManager: clearing the identity map between requests under scenario B remains the application's responsibility (stale `SeoSurface` copies can otherwise be served until the app clears it). Creating the singleton row at deploy time is still recommended. Tests: `SeoRepositoriesTest::testSiteSettingsGetOrCreateReturnsRowCreatedConcurrentlyByAnotherWorker`, `testSiteSettingsSaveResetsClosedManagerAndRethrows`, `testSurfaceGetOrCreateReturnsRowCreatedConcurrently`, rethrow tests.

### W-04 — `SeoRuntime` is cleared on `kernel.terminate`, not by the resetter (Low)

- **Where:** `src/Service/SeoRuntime.php:13`, `:16` (`$overrides`, `$variables`), `:21-24` and `:31-34` (`array_replace` merges into the existing state), `:52-56` (`clear()`); cleared by `src/EventSubscriber/SeoRuntimeClearSubscriber.php:22-30` on `KernelEvents::TERMINATE`. Read by `SeoMetadataResolver::resolve()` (`src/Service/SeoMetadataResolver.php:116-127`), written by host controllers and `PageHeadRuntimeBridge::apply()` (`src/Service/PageHeadRuntimeBridge.php:62`).
- **Worker impact:** the Symfony FrankenPHP runner calls `$kernel->terminate()` after each handled request, so the bag is cleared under both scenarios. It is not cleared if terminate is skipped: a custom worker loop that only calls `handle()`, or a request where the runner has no response to terminate. In that case the next request merges its overrides on top of the previous ones, and the leftover keys (title, canonical, `noindex`, JSON-LD) appear in another page, possibly for another user. When a terminate listener throws, the exception normally ends the worker script, which FrankenPHP restarts, so the state is dropped with it.
- **Recommendation:** implement `ResetInterface` on `SeoRuntime` (delegating to `clear()`) as defense in depth, and keep the terminate subscriber.
- **Status:** Resolved — `src/Service/SeoRuntime.php` implements `ResetInterface` (`reset()` → `clear()`), and the bag is also cleared at the start of every main request, so a skipped `terminate()` no longer leaks overrides. The terminate listener is kept. Tests: `SeoRuntimeTest`, `SeoRuntimeClearSubscriberTest`.

Info / observations:

- `SeoPathBuilder::absoluteUrl()` (`src/Service/SeoPathBuilder.php:25-37`) falls back to `$request->getSchemeAndHttpHost()` when `base_url` is not configured. This is computed per request and does not leak between requests, but canonical, hreflang, sitemap and robots URLs then follow the `Host` header, and `/sitemap.xml` is sent with `Cache-Control: public, max-age=3600` (`src/Controller/SeoKitController.php:36-39`). Configure `base_url` (and `framework.trusted_hosts`) in production. Not specific to worker mode.
- `SeoStaticRouteLoader` (`src/Routing/SeoStaticRouteLoader.php:25`, `:39-42`) and `SeoAdminRouteLoader` (`src/Routing/SeoAdminRouteLoader.php:16`, `:29-32`) throw on a second `load()`. They only run while the router cache is built (warmup), so this does not affect production workers. In a dev worker without `watch`, route changes are not picked up until the worker restarts.
- `SeoMetadataResolver`, `PageHeadResolver` and the sitemap/robots generators are fully stateless and derive everything from the request passed in; no per-request result is cached across requests.

## Usage recommendations in worker mode

- The bundle is safe with or without Symfony's `services_resetter`; keeping it active is still recommended for framework services (Doctrine identity map, security token).
- Under scenario B the application must clear its own EntityManager between requests.
- Call `SeoRuntime::set()` / `PageHeadContext::describe()` from controllers or from `kernel.request` listeners with priority < 4096.
- Configure `nowo_seo_kit.base_url` so URLs never depend on the `Host` header.
- Create the `SeoSiteSettings` singleton row during deployment (migration or fixture) so the page-render path never writes.
- Custom `SeoDefaultsProviderInterface`, `PageHeadDefaultsProviderInterface`, `PageHeadSiteGraphProviderInterface`, `PageHeadTitleComposerInterface`, `SitemapUrlProviderInterface`, `SiteIndexabilityProviderInterface` and `SeoAuditSubjectProviderInterface` services must stay stateless or implement `ResetInterface`. Do not memoize per-page data in them.
- Always set runtime overrides through `SeoRuntime` / `PageHeadContext` inside the request; never keep a `PageHead` or override array in your own service properties.

## Re-audit triggers

Re-run this audit when a change adds: a new property or memo to any service (especially `PageHeadContext`, `SeoSiteConfigProvider`, `SeoRuntime`, `SeoMetadataResolver`), a result cache for resolved metadata, a new event listener, a new write on the page-render path, removal of a `ResetInterface` implementation or `kernel.reset` tag, or a dependency on `$_SERVER` / `$_ENV` at runtime.
