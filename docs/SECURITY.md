# Security

## Table of contents

- [Reporting vulnerabilities](#reporting-vulnerabilities)
- [Attack surface and threat model](#attack-surface-and-threat-model)
- [Admin UI access (REQ-UI-002)](#admin-ui-access-req-ui-002)
- [CSRF on session mutations (REQ-SEC-005)](#csrf-on-session-mutations-req-sec-005)
- [Logging](#logging)
- [Integrator guidance](#integrator-guidance)
- [Dependencies](#dependencies)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)
- [AI security audit (REQ-SEC-004)](#ai-security-audit-req-sec-004)

## Reporting vulnerabilities

Report security issues privately to **hectorfranco@nowo.tech**. Do not open public issues for sensitive reports.

See [.github/SECURITY.md](../.github/SECURITY.md) for supported versions.

## Attack surface and threat model

| Surface | Exposure | Protection |
|---------|----------|------------|
| `GET /sitemap.xml`, `GET /robots.txt` (bundle route loader) | Public, unauthenticated | Read-only; URLs built from config and tagged providers only, never from request input; absolute URLs use `base_url`; without it they follow the request `Host` header (`OriginUrlGuard` only validates canonical overrides), so set `base_url` (the recipe uses `%env(default::DEFAULT_URI)%`) or `framework.trusted_hosts` before the public sitemap (cached `public, max-age=3600`) goes behind a shared cache. |
| Twig `nowo_seo_head()` / `nowo_seo_metadata()` | Every page that renders them | Values are escaped by Twig; JSON-LD is encoded with `<` / `>` escaped so it cannot break out of `<script>`. |
| Admin UI `/settings/seo`, `/admin/seo/surfaces` or `<web_ui.path_prefix>/settings`, `/surfaces` (`web_ui.enabled`, off by default) | Session-authenticated | `SeoKitAccessCheckerInterface` on every action (default: any of `security.access_roles`, default `ROLE_ADMIN`); Symfony Forms with CSRF; canonical overrides restricted to the site origin; image URLs limited to site paths / https; no control characters in URLs. |
| JSON pencil API `GET/POST /_nowo/seo/surfaces/{key}/{locale}` (`web_ui.api_enabled`) | Session-authenticated | Same access checker; locales that are not enabled and keys over 180 characters answer 404; `POST` requires `Content-Type: application/json` **and** an `X-CSRF-Token` header (see below); payload goes through the same validated `SeoSurfaceType`. |
| `nowo:seo:audit` console command | Operators only | Read-only report over tagged audit subjects. |
| Doctrine entities (`persistence.enabled`, off by default) | Database | Bundle-owned tables (optionally prefixed with `doctrine.table_prefix`); no raw SQL. |

Threats considered: SEO spam / open redirect through canonical overrides (blocked by the origin guard), stored XSS through SEO fields (Twig escaping + JSON-LD encoding), cross-site request forgery against the admin UI and the JSON API (Form CSRF + header token; the container refuses to compile with `web_ui.enabled` and form CSRF disabled — `CsrfProtectionPass`), an admin UI left open by configuration (empty role list or `PUBLIC_ACCESS` refused; no SecurityBundle → compile error — `WebUiSecurityPass`), secrets leaking into the head (request attributes named like nonces / tokens / passwords are not title placeholders), host-header poisoning of absolute URLs (set `base_url` in production). Out of scope: authentication itself (the host firewall) and the content of host-provided providers.

## Admin UI access (REQ-UI-002)

- Two layers: the host firewall (`access_control` on the admin paths and `^/_nowo/seo/`, see
  [CONFIGURATION.md — Host access_control](CONFIGURATION.md#host-access_control)) and the bundle access checker on
  every admin and API action.
- `security.access_roles` (default `[ROLE_ADMIN]`): the default checker grants a user with **any** listed role and
  denies everybody when the list is empty (fail closed); an empty list is refused at container build time unless
  `security.access_checker` names a host service. `PUBLIC_ACCESS` / `IS_AUTHENTICATED_ANONYMOUSLY` are rejected.
- `security.access_checker`: host service implementing `SeoKitAccessCheckerInterface` (teams, voters, …).
- **`security.allow_unauthenticated: true` is a footgun:** it makes the admin UI and the JSON API public (no login,
  no role) and drops the SecurityBundle requirement. Use it only in a local demo; never in production.
- With `web_ui.enabled` and `allow_unauthenticated: false` the container does not compile without SecurityBundle
  (`WebUiSecurityPass`).

## CSRF on session mutations (REQ-SEC-005)

- Admin forms (`SeoSiteSettingsType`, `SeoSurfaceType`) keep Symfony Form CSRF enabled; the container refuses to compile while `web_ui.enabled` without `security.csrf.token_manager`, the form CSRF extension, or with `framework.form.csrf_protection: false` (`CsrfProtectionPass`).
- The JSON API `POST` requires a token in the `X-CSRF-Token` header (token id `nowo_seo_kit_surface_api`); `GET` on the same URL returns it as `csrf_token`. Validation is fail-closed: without a CSRF token manager (`symfony/security-csrf` + `framework.csrf_protection`) every `POST` is refused with 403.
- `web_ui.api_csrf_protection: false` disables the header check. Use it only when the API sits behind a Bearer / API-token firewall without session cookies, and document it in the host.

## Logging

- The bundle logs one warning when stored site settings cannot be read (`SeoSiteConfigProvider` falls back to deployment defaults); the context holds the exception message only.
- It never logs request bodies, CSRF tokens, session identifiers, user data or SEO field values.
- No `dump()` / `error_log()` in `src/`.

## Integrator guidance

- Set `base_url` in production when generating canonicals/sitemap behind proxies or in CLI.
- Use `noindex` on slugs or runtime overrides for private or draft content.
- Keep `robots.disallow` aligned with admin and API paths.
- Do not expose internal hostnames in `base_url` or canonical overrides.
- Escape/encode user-controlled SEO fields in Twig (`title`, `description`, Open Graph values).
- The surface SEO JSON API (`POST /_nowo/seo/surfaces/{key}/{locale}`) is protected by the admin role, a required `Content-Type: application/json` and an `X-CSRF-Token` header (see [CSRF](#csrf-on-session-mutations-req-sec-005)). Do not enable permissive CORS (credentials + wildcard origins) on `/_nowo/seo/`.
- Set `doctrine.table_prefix` when several applications share one database.
- Raw `json_ld.json` strings are rendered `|raw`; the resolver encodes `<` / `>` as `\u003C` / `\u003E` (like `JSON_HEX_TAG`) so the value can neither close the `<script>` element nor open `<!--`, but keep the JSON itself trusted.

## Dependencies

Run `composer audit` in consuming applications and keep Symfony/Twig updated per your support policy.

## Release security checklist (12.4.1)

Before tagging a release, confirm:

| Item | Notes |
|------|--------|
| **SECURITY.md** | This document is current and linked from the README where applicable. |
| **`.gitignore` and `.env`** | `.env` and local env files are ignored; no committed secrets. |
| **No secrets in repo** | No API keys, passwords, or tokens in tracked files. |
| **Recipe / Flex** | Default recipe or installer templates do not ship production secrets. |
| **Input / output** | Inputs validated; outputs escaped in Twig/templates where user-controlled. |
| **Dependencies** | `composer audit` run; issues triaged. |
| **Logging** | Logs do not print secrets, tokens, or session identifiers unnecessarily. |
| **Cryptography** | If used: keys from secure config; never hardcoded. |
| **Permissions / exposure** | Routes and admin features documented; `security.access_roles` configured, `allow_unauthenticated` false, host `access_control` on the admin paths. |
| **Limits / DoS** | Sitemap/robots generation stays bounded; no unbounded crawl of untrusted URLs. |
| **CSRF** | Admin forms keep CSRF; JSON API writes require `X-CSRF-Token` (REQ-SEC-005). |
| **AI security audit** | Grade recorded in org `BUNDLES_SECURITY_ANALYSIS.md` when applicable (REQ-SEC-004); re-audit after changes to the admin UI or JSON API. |

Record confirmation in the release PR or tag notes.


## AI security audit (REQ-SEC-004)

| Field | Value |
|-------|--------|
| Date | 2026-10-28 (supersedes 2026-07-29, Pass good) |
| Method | Claude Code static security review of `main` `ba8f407` (v1.17.0 + 3); org record `BUNDLES_SECURITY_ANALYSIS.md` Appendix AR |
| Grade | **Pass (conditional)** |
| Overall risk | **Low** |
| Open Critical / High / Medium | None |
| Findings | L1 absolute URLs from the request `Host` without `base_url`; L2 admin forms fail open when the host disables form CSRF; L3 stale supported versions; Info I1–I7 |
| Remediation | 1.18.0: L1 (recipe `base_url: '%env(default::DEFAULT_URI)%'`, threat-model note), L2 (`CsrfProtectionPass`). Unreleased: L3 (`.github/SECURITY.md` 1.18.x), I1 (control characters in canonical / image URLs), I2 (image URL fields of the site settings), I3 (API locale / key length), I4 (secret-looking request attributes are not placeholders), I5 (single-encoded edit links), I6 (`security.access_roles` fail closed); I7 (sitemap chunking) stays on the roadmap |
| Next | Independent re-audit of the admin UI rework (UiKit page contract, access checker) before the next release grade is recorded |
