# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/seo-kit-bundle` (`symfony-bundle`) |
| Audited revision | `v1.10.1`; boundaries re-audited for 1.23.1 (REQ-RUNTIME-002, 2026-11-11) |
| Audit date | 2026-09-25 (Messenger / console boundaries: 2026-11-11) |
| Method | Manual review of every file under `src/` (services, persistence providers, repositories, Twig extension, controllers, event subscriber, route loaders, form types, audit command), `src/Resources/config/*.yaml`, the DI extension and compiler pass |
| **Verdict** | ✅ **Viable under scenario B** — `SeoRuntimeClearSubscriber` clears `SeoRuntime`, `PageHeadContext` and the site settings memo at every unit-of-work boundary (main request start, `kernel.terminate`, Messenger message received / handled / failed, console command start / terminate); repository flushes recover a closed EntityManager. Clearing the application's EntityManager identity map between requests remains the application's responsibility |
| Original verdict | ⚠️ Viable with conditions — safe only when `services_resetter` runs (scenario A) |
| Remediation | W-01…W-04 resolved in **v1.10.1**: request-start reset in `SeoRuntimeClearSubscriber` (`kernel.request` 4096, main only), `SeoRuntime implements ResetInterface`, `ResetsClosedEntityManagerTrait` + unique-violation recovery in both repositories, refresh of the managed settings row. Regression tests simulate consecutive requests on the same instances without `reset()`. 1.23.0: the reset only ever touches the manager of the SeoKit entities (optionally a dedicated one, `doctrine.entity_manager`), never a host manager; kept-alive kernel test `tests/Integration/Worker/WorkerEntityManagerRecoveryTest.php`. 1.23.1: W-05 (Messenger and console boundaries), one-kernel test `tests/Integration/Worker/WorkerBoundariesTest.php` |

## Table of contents

- [Execution model assumed](#execution-model-assumed)
- [Summary](#summary)
- [Services reviewed](#services-reviewed)
- [Findings](#findings)
  - [W-01 — `PageHeadContext` keeps the previous page head without a reset (Medium)](#w-01--pageheadcontext-keeps-the-previous-page-head-without-a-reset-medium)
  - [W-02 — Site settings are memoized per worker (Medium)](#w-02--site-settings-are-memoized-per-worker-medium)
  - [W-03 — Failed `flush()` on the read path is swallowed and leaves a closed EntityManager (Medium, scenario B only)](#w-03--failed-flush-on-the-read-path-is-swallowed-and-leaves-a-closed-entitymanager-medium-scenario-b-only)
  - [W-04 — `SeoRuntime` is cleared on `kernel.terminate`, not by the resetter (Low)](#w-04--seoruntime-is-cleared-on-kernelterminate-not-by-the-resetter-low)
  - [W-05 — No clear at the Messenger and console boundaries (Medium)](#w-05--no-clear-at-the-messenger-and-console-boundaries-medium)
- [Boundary events](#boundary-events)
- [Worker test](#worker-test)
- [Host rule: entity managers](#host-rule-entity-managers)
- [Usage recommendations in worker mode](#usage-recommendations-in-worker-mode)
- [Igor findings triage (2026-11-11)](#igor-findings-triage-2026-11-11)
- [Re-audit triggers](#re-audit-triggers)

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

**Precondition, precisely.** `FRANKENPHP_RESET_KERNEL=false` alone does not mean scenario B: with the stock Symfony kernel, `Kernel::handle()` → `boot()` still calls `services_resetter->reset()` before every request after the first. Scenario B happens where the resetter does not run: a host that removes, replaces or bypasses `services_resetter` (or drives `http_kernel` directly), Messenger consumers started with `messenger:consume --no-reset`, and long-running console processes. This bundle is correct in all of them.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `PageHeadContext` (`$input`, `$resolved`), `SeoSiteConfigProvider` (`$config`), `SeoRuntime` (`$overrides`, `$variables`) are cleared by the bundle at every unit-of-work boundary (W-01, W-02, W-04, W-05); everything else is `readonly` or has no properties |
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
| `Service\PageHeadContext` (only when `base_url` is set) | yes | `$input`, `$resolved`; `kernel.reset` + cleared at every boundary | ✅ | ✅ |
| `Service\Persistence\SeoSiteConfigProvider` (persistence enabled) | yes | `$config` (request memo); `kernel.reset` + cleared at every boundary; cross-worker freshness via `cache.app` | ✅ | ✅ |
| `Service\SeoRuntime` | yes (`shared: true`) | `$overrides`, `$variables`; `ResetInterface` + cleared at every boundary | ✅ | ✅ |
| `EventSubscriber\SeoRuntimeClearSubscriber` (see [Boundary events](#boundary-events)) | yes | console nesting depth (bounded) and in-flight message flag, maintained at the boundaries; iterator of `IGNORE_ON_UNINITIALIZED_REFERENCE` references | ✅ | ✅ |
| `Service\SeoMetadataResolver` | yes | none (`final readonly`) | ✅ | ✅ |
| `Service\PageHeadResolver`, `HreflangSetBuilder`, `AbsoluteUrlBuilder`, `PageHeadRuntimeBridge` | yes | none (`final readonly`) | ✅ | ✅ |
| `Service\SeoPathBuilder`, `SeoTemplateRenderer`, `OriginUrlGuard`, `SeoPencilCatalogFactory`, `StructuredData\SiteStructuredDataFactory` | yes | none | ✅ | ✅ |
| `Service\SitemapGenerator`, `RobotsTxtGenerator` | yes | none (`final readonly`; the provider de-duplication set is a local variable per call) | ✅ | ✅ |
| `Service\Persistence\DoctrineSeoDefaultsProvider` | yes | none (`final readonly`); reads `SeoSiteConfigProvider` | ✅ | ✅ |
| `Service\SeoSurfaceManager` (also `SeoSurfaceStoreInterface` since 1.24.0) | yes | none (`final readonly`) | ✅ | ✅ |
| `Service\SitemapContextFactory` (1.24.0, static), `Model\SitemapContext` / `Model\SeoSurfaceData` (1.24.0 value objects, built per call) | n/a | none (`readonly` VOs) | ✅ | ✅ |
| `Service\SeoChangeNotifier` | yes | none (`final readonly`) | ✅ | ✅ |
| `Service\Audit\SeoAuditor`, `SeoAuditRules` | yes | none | ✅ | ✅ |
| `Repository\SeoSiteSettingsRepository`, `SeoSurfaceRepository` | yes | none (only `readonly` fallback strings and the registry) | ✅ | ✅ |
| `Doctrine\SeoKitEntityManager` (1.24.0, zero-config) | yes | the private manager and its stale flag; dropped at every boundary and at `kernel.reset`, replaced when closed or stale | ✅ | ✅ |
| `Doctrine\SeoKitManagerRegistry`, `PrivateEntityManagerMiddleware` (1.24.0) | yes | none (`final readonly`) | ✅ | ✅ |
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

### W-05 — No clear at the Messenger and console boundaries (Medium)

- **Where:** `src/EventSubscriber/SeoRuntimeClearSubscriber.php` subscribed only to `kernel.request` (main) and `kernel.terminate` (1.0.0 to 1.23.0); `PageHeadContext` and the site settings memo were not cleared at `kernel.terminate` either.
- **Worker impact:** a Messenger consumer has no `kernel.request`. With `messenger:consume --no-reset` (or a host without `services_resetter`), an e-mail rendered in a handler saw the previous message's `SeoRuntime` overrides (title, description, `noindex`, variables) and described page head, possibly another tenant's, and kept an old site settings snapshot after another worker edited them. The same between console commands run in one long-lived process.
- **Status:** Resolved in 1.23.1 — the subscriber clears all three services at `kernel.terminate`, `WorkerMessageReceivedEvent`, `WorkerMessageHandledEvent`, `WorkerMessageFailedEvent`, `console.command` and `console.terminate` too. Tests: `tests/Integration/Worker/WorkerBoundariesTest.php`, `SeoRuntimeClearSubscriberTest`.

Info / observations:

- `SeoPathBuilder::absoluteUrl()` (`src/Service/SeoPathBuilder.php:25-37`) falls back to `$request->getSchemeAndHttpHost()` when `base_url` is not configured. This is computed per request and does not leak between requests, but canonical, hreflang, sitemap and robots URLs then follow the `Host` header, and `/sitemap.xml` is sent with `Cache-Control: public, max-age=3600` (`src/Controller/SeoKitController.php:36-39`). Configure `base_url` (and `framework.trusted_hosts`) in production. Not specific to worker mode.
- `SeoStaticRouteLoader` (`src/Routing/SeoStaticRouteLoader.php:25`, `:39-42`) and `SeoAdminRouteLoader` (`src/Routing/SeoAdminRouteLoader.php:16`, `:29-32`) throw on a second `load()`. They only run while the router cache is built (warmup), so this does not affect production workers. In a dev worker without `watch`, route changes are not picked up until the worker restarts.
- `SeoMetadataResolver`, `PageHeadResolver` and the sitemap/robots generators are fully stateless and derive everything from the request passed in; no per-request result is cached across requests.

## Usage recommendations in worker mode

- The bundle is safe with or without Symfony's `services_resetter`; keeping it active is still recommended for framework services (Doctrine identity map, security token).
- Under scenario B the application must clear its own EntityManager between requests (see [Host rule](#host-rule-entity-managers)).
- One console command or one Messenger message is **one** unit of work: a command that renders pages or e-mails for several tenants in a loop must call `SeoRuntime::clear()` and `PageHeadContext::reset()` itself between tenants.
- Call `SeoRuntime::set()` / `PageHeadContext::describe()` from controllers or from `kernel.request` listeners with priority < 4096.
- Configure `nowo_seo_kit.base_url` so URLs never depend on the `Host` header.
- Create the `SeoSiteSettings` singleton row during deployment (migration or fixture) so the page-render path never writes.
- Custom `SeoDefaultsProviderInterface`, `PageHeadDefaultsProviderInterface`, `PageHeadSiteGraphProviderInterface`, `PageHeadTitleComposerInterface`, `SitemapEntryProviderInterface` (and the deprecated `SitemapUrlProviderInterface`), `SiteIndexabilityProviderInterface` and `SeoAuditSubjectProviderInterface` services must stay stateless or implement `ResetInterface`. Do not memoize per-page data in them.
- Always set runtime overrides through `SeoRuntime` / `PageHeadContext` inside the request; never keep a `PageHead` or override array in your own service properties.

## Boundary events

| Boundary | Event (subscribed by name) | Priority | Cleared |
|---|---|---|---|
| HTTP main request start | `kernel.request` (main only) | 4096 | `SeoRuntime`, `PageHeadContext`, `SeoSiteConfigProvider` memo |
| HTTP end | `kernel.terminate` | 0 | same |
| Messenger message start | `Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent` | 4096 | same |
| Messenger message end | `WorkerMessageHandledEvent`, `WorkerMessageFailedEvent` | -4096 | same |
| Console command start / end | `console.command` / `console.terminate` | 4096 / -4096 | same, only for the outermost command (depth 0→1 / 1→0) and only while no Messenger message is in flight |
| Messenger worker stopped | `WorkerStoppedEvent` | -4096 | clears the in-flight flag only |
| Services resetter (when the host runs it) | `kernel.reset` | — | same (kept, not relied on) |

Since 1.24.0 the bundle's private entity manager (`Doctrine\SeoKitEntityManager`, zero-config mode) is one of the
`$resettables`: it is **dropped** (cleared, then forgotten; the next call creates a fresh one) at the same boundaries,
with the same nested-command and in-flight-message guards, and at `kernel.reset`. A manager closed by its own failed
flush, or made stale by a rollback on the connection (doctrine/dbal 4 middleware), is replaced on its next use, unless
it still has scheduled work. Test: `tests/Integration/PrivateEntityManager/PrivateEntityManagerTest.php`
(300 requests through `http_kernel` without the resetter while another worker edits the row; nested console commands).

A command run in-process from another command or from a message handler (`Application::doRun()`) fires `console.command` / `console.terminate` too; the subscriber counts the nesting depth and keeps an in-flight message flag (set on received, unset on handled / failed / worker stopped), so such a nested run never clears the outer unit's overrides and page head (audit MI-S-L2; same pattern as BeaconBundle 1.24.0). Tests: `WorkerBoundariesTest::testNestedCommandKeepsTheOuterCommandsState`, `testCommandRunFromAMessageHandlerKeepsTheHandlersState`.

The services reach the subscriber through `$resettables`, an iterator of `IGNORE_ON_UNINITIALIZED_REFERENCE` references (`'@!…'` in `services.yaml`): a boundary only clears what the unit of work built and never builds the Doctrine-backed provider itself.

## Worker test

`tests/Integration/Worker/WorkerBoundariesTest.php` boots **one** kernel (`BoundariesTestKernel`: Framework, Twig, Doctrine on in-memory SQLite, SeoKit with persistence and the page head) and never calls `services_resetter`:

- **HTTP:** 500 requests through `http_kernel` (not `Kernel::handle()`, so no resetter) to two pages, `/page/A` and `/page/B`, each followed by `terminate()`;
- **Messenger:** 500 messages through a real `Symfony\Component\Messenger\Worker` on the kernel's dispatcher, without `ResetServicesListener` (as `--no-reset`); every 7th handler fails;
- **Console:** 500 `Symfony\Component\Console\Application::run()` calls on the kernel's dispatcher.

Two tenants alternate. Each unit records what it found, then sets its own private overrides, variables and page head and leaves them in place. Before each unit "another worker" renames the site in the database and deletes the shared `cache.app` entry, as the admin's `refresh()` does. Each unit must start with no overrides, no described head and the current site name; memory margin: less than 1 MiB growth after a 50-unit warm-up. Without the 1.23.1 boundaries the Messenger and console cases fail (tenant B starts with tenant A's title, `noindex`, slug and page head, and with the old site name). `clearState()` and every `reset()` are also tested directly (`SeoRuntimeClearSubscriberTest`, `SeoRuntimeTest`, `PageHeadContextTest`, `SeoSiteConfigProviderTest`).

Static checks: `composer igor` (93 service files, 0 KO, 0 WARN) and PHPStan with the FrankenPHP classic + worker rulesets pass.

## Host rule: entity managers

A host that disables or bypasses the services resetter must also clear its **own** entity managers at every boundary (main request start or end, each Messenger message, each console command); otherwise the identity map serves stale rows (for example a downgraded permission or a soft-deleted item) across requests (REQ-RUNTIME-002 rule 7). SeoKit's own rows are refreshed on read (`SeoSiteSettingsRepository::getOrCreate()`), and a closed manager of the SeoKit entities is recovered by the bundle. Since 1.24.0 SeoKit never touches the host's managers in zero-config mode (private manager), so the host rule applies only to the host's own entities.

## Igor findings triage (2026-11-11)

`composer igor` (igor-php 0.10.1) reported 1 warning on `main` (2 properties of `SeoRuntime`); false positives. Igor now
runs in CI (`igor` job) and reports 0 KO / 0 WARN.

| Finding | Verdict | Resolution |
|---------|---------|------------|
| `SeoRuntime::$overrides` / `$variables` "mutated but not reset in reset()" | False positive | `reset()` already emptied both through `clear()`, and `SeoRuntimeClearSubscriber` clears them at the start of every main request and on `kernel.terminate` (scenario B); igor does not follow the call. `reset()` now assigns both directly. |

## Re-audit triggers

Re-run this audit when a change adds: a new property or memo to any service (especially `PageHeadContext`, `SeoSiteConfigProvider`, `SeoRuntime`, `SeoMetadataResolver`), a result cache for resolved metadata, a new event listener, a new write on the page-render path, removal of a `ResetInterface` implementation or `kernel.reset` tag, or a dependency on `$_SERVER` / `$_ENV` at runtime.
