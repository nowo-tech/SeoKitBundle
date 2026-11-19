# Concurrency

How SeoKitBundle writes under contention (REQ-RUNTIME-003): what each write path does when two requests, two
workers or a request and a console command write at the same time, and what a client gets back when a lock conflict
stops a write. Everything here applies only with `persistence.enabled: true`; without persistence the bundle writes
nothing.

## Table of contents

- [Write paths](#write-paths)
- [Conflict answers and codes](#conflict-answers-and-codes)
- [Unique keys are not conflicts](#unique-keys-are-not-conflicts)
- [Host options](#host-options)
- [Worker mode and DDD hosts](#worker-mode-and-ddd-hosts)
- [Tests](#tests)
- [Residual risks](#residual-risks)

## Write paths

Every write is **one flush** of the bundle's entity manager: the private manager by default
(`doctrine.private_entity_manager`), or the manager named in `doctrine.entity_manager`. The bundle never flushes,
clears or resets an application manager. A flush is one transaction (the ORM opens it, or a savepoint inside a
transaction the host opened). Change events (`SeoSurfaceChangedEvent`, `SeoSiteSettingsChangedEvent`) are dispatched
after the flush returned.

| Path | Transaction | Control | Conflict answer |
| --- | --- | --- | --- |
| Surface override save: admin `POST /admin/seo/surfaces/{locale}/edit/{key}`, JSON API `POST /_nowo/seo/surfaces/{key}/{locale}`, `SeoSurfaceStoreInterface::save()`, `SeoSurfaceManagerInterface::saveOrClear()` (`SeoSurfaceRepository::upsert()`) | one flush | Unique key `uniq_nowo_seo_surface_key_locale` (one row per key and locale) plus catch: when another request inserted the same key and locale first, the overrides are written to that row (last writer wins, as for two edits of an existing row) | Lock conflict on a bundle route: 409 `CONFLICT_001` |
| Surface override clear (all fields empty), `SeoSurfaceStoreInterface::clear()` | one flush (DELETE) | Deleting a row another request already deleted affects nothing and is not an error | 409 `CONFLICT_001` |
| `SeoSurfaceRepository::getOrCreate()` / `SeoSurfaceManagerInterface::getOrCreate()` | one flush (INSERT) | Same unique key plus catch: the row of the request that won is returned | Host code: the failure is thrown |
| Site settings seed (`SeoSiteSettingsRepository::getOrCreate()`: the settings page, `SeoSiteConfigProvider` on the first read) | one flush (INSERT) | Primary key `id = 1` (a single row) plus catch: the row of the request that won is returned | 409 `CONFLICT_001`, or `CONFLICT_002` when a commit happened earlier in the request |
| Site settings save (`POST /settings/seo`) with its translations | one flush (UPDATE + INSERTs of new translations) | Last writer wins on the row; unique key `uniq_nowo_seo_site_settings_translation_locale` (one translation per locale) | 409 `CONFLICT_001`; **`CONFLICT_002`** when the same request seeded the settings row first; a concurrent new translation of the same locale: domain 409 `SEO_KIT_DUPLICATE_KEY` |

There is no version column: two admins editing the same surface or the settings at once both succeed, and the last
save wins (see [Residual risks](#residual-risks)). There are no counters and no `MAX()+1` ids.

## Conflict answers and codes

A lock conflict (deadlock, lock wait timeout, serialization failure, a `TransientStorageFailure` of a runner, or a
lost savepoint of unknown outcome) on a route of the bundle answers **409** (configurable to 503) instead of a 500,
with a conflict code from nowo-tech/transaction-doctrine in the `X-Conflict-Code` header:

| Code | When | Headers | What the client does |
| --- | --- | --- | --- |
| `CONFLICT_001` | Nothing of the request was committed | `X-Conflict-Code: CONFLICT_001`, `Retry-After` (default 1 s) | Retry after `Retry-After` |
| `CONFLICT_002` | Part of the request may already be committed: a commit earlier in the same request (for example the settings page seeded the settings row before its save hit the lock), a committed `ConcurrencyConflict`, or a lost savepoint with no deadlock behind it | `X-Conflict-Code: CONFLICT_002`, **no** `Retry-After` | Reload, then apply the change again; do not repeat blindly |

"Committed earlier in the same request" is read from a commit watermark taken when the main request starts
(`ConflictCode::forRequest()`): transaction-doctrine's request mark when its bundle is registered with
`commit_detection`, and the bundle's own mark on a `CommitLedger` fed by a commit-tracking middleware on the
connection of the SeoKit entities only (see `conflict_response.commit_tracking`).

Shapes (the existing keys are kept, `code` is added):

- JSON surface API: `{"ok": false, "errors": ["…"], "code": "CONFLICT_001"}` with the status and headers above.
- Admin and public pages: an `HttpException` with the status and headers above and a fixed, client-safe message,
  rendered by the application's error page; the storage error is its previous exception (for the logs). No SQL or
  value reaches the client.

Only the bundle's controllers are answered. The host's routes, and the host's own calls of the repositories,
`SeoSurfaceStoreInterface` or `SeoSurfaceManagerInterface`, get the failure thrown as it is (a transient failure is
never swallowed), so the host's own conflict handling applies.

Messenger: the bundle dispatches no message and handles none. A host handler that calls the store and fails after
another handler of the same message succeeded is classified by transaction-doctrine (`HandlerFailedException` with a
`HandledStamp`, or `DelayedMessageHandlingException`: `CONFLICT_002`).

## Unique keys are not conflicts

A unique-key violation is never answered with a `CONFLICT_*` code:

- the expected "only one" races (the surface key and locale, the settings row) are caught where they happen and give
  the domain result described in [Write paths](#write-paths);
- one settings translation per locale (two admins adding the same missing translation at once) answers **409** with
  the domain code `SEO_KIT_DUPLICATE_KEY` in `X-Error-Code`, no `X-Conflict-Code` and no `Retry-After`: reload the
  page and apply the change again. Only the settings page maps it; a unique violation on any other route of the
  bundle is a real error;
- a lost connection or any other storage error is not mapped: it is a real error (500).

## Host options

```yaml
nowo_seo_kit:
    conflict_response:
        status: ~             # 409 (default) or 503; null follows transaction-doctrine's conflict_response, else 409
        retry_after: ~        # Retry-After seconds of a CONFLICT_001 answer; null follows transaction-doctrine, else 1
        commit_tracking: bundle   # bundle (default) or off
```

- `status: 503` keeps the pre-1.25 status for hosts whose clients expect it; the conflict code and the `Retry-After`
  rule stay the same.
- `commit_tracking: bundle` registers transaction-doctrine's `CommitTrackingMiddleware` with a bundle-owned ledger on
  the connection of the manager that holds the SeoKit entities only (`doctrine.entity_manager`, else the default
  manager); the host's other connections are never wrapped (audit UE-CF-1). When NowoTransactionDoctrineBundle is
  registered with `commit_detection: true`, its ledger is reused and the bundle adds no middleware. `off` registers
  neither: only transaction-doctrine's request mark (when present) and the failure itself decide the code.

## Worker mode and DDD hosts

- FrankenPHP worker mode with the kernel reset off: the conflict listener is stateless; the commit ledger is a
  monotonic counter read by difference (the mark lives on the request). The private manager is dropped at every
  unit-of-work boundary and replaced when a failed flush closed it, so the next request after a conflict writes
  normally (covered by the race tests).
- DDD hosts (`doctrine.entity_manager` on a dedicated manager and connection): the same rules; the middleware follows
  that manager's connection.

## Tests

- `tests/Database/WritePathRaceTest.php`: two real connections on MySQL 8.4, MariaDB 11.4 and PostgreSQL 16 (CI job
  "Real databases", with doctrine/dbal 4 and 3). Each session sets `innodb_lock_wait_timeout` / `lock_timeout` to
  2 seconds so a wait fails fast. Covered: the surface insert race (one row, last writer wins, the next write of the
  worker works), the settings seed race, the duplicate translation (domain 409, no conflict code), a row lock held
  by another request (`CONFLICT_001` with `Retry-After`, nothing of the request committed, the retry succeeds), and a
  lock conflict after a commit of the same request (`CONFLICT_002`, no `Retry-After`).
- `tests/Unit/EventSubscriber/SeoKitConflictListenerTest.php`: the mapping (001, 002 through the bundle's mark and a
  sub-request, lost savepoint, runner hint, 503, unique violation, lost connection and host routes left alone).

## Residual risks

- **Last writer wins** on concurrent edits of the same surface or of the settings (no version column): the earlier
  save is overwritten without a warning. Acceptable for SEO metadata edited by a few admins; a host that needs
  optimistic locking wraps the store in its own versioned model.
- **Update of a row deleted meanwhile**: an admin saving a surface another admin cleared at the same moment updates
  no row; the save reports success but the override stays cleared. Save again.
- **`saveOrClear()` with a new entity that lost the insert race**: the overrides are written to the stored row, which
  `upsert()` returns; the entity the host passed is not stored (it belonged to the manager the failed flush closed).
  Host code that needs the stored row uses `SeoSurfaceRepository::upsert()` (or the entity-free `SeoSurfaceStoreInterface`).
- **A host transaction around a bundle write on PostgreSQL with doctrine/dbal 3** (no savepoints unless the host
  enables them): a refused insert aborts the host's whole transaction, so the "only one" catch cannot read the
  winner's row and the failure is thrown. DBAL 4 always nests with savepoints.
- **doctrine/dbal 3**: the private manager has no rollback detection (see `doctrine.private_entity_manager` in
  [CONFIGURATION.md](CONFIGURATION.md#doctrine)); reload before repeating a save that a host rollback undid.
- **doctrine/dbal 3 with `commit_tracking: bundle`**: the commit-tracking middleware wraps the connection of the
  SeoKit entities, which is usually the host's own connection; on DBAL 3 a wrapped driver connection's `commit()`
  returns `null` instead of `true`. A host that reads that return value sets `commit_tracking: off` or enables
  transaction-doctrine's `commit_detection` (then the bundle adds no middleware).
- **`commit_tracking: off`** without transaction-doctrine's `commit_detection`: a conflict after an earlier commit of
  the same request answers `CONFLICT_001`; a retry then repeats only idempotent work (the seed is a get-or-create).
