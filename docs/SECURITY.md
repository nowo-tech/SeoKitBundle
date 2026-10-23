# Security

## Table of contents

- [Reporting vulnerabilities](#reporting-vulnerabilities)
- [Attack surface and threat model](#attack-surface-and-threat-model)
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
| `GET /sitemap.xml`, `GET /robots.txt` (bundle route loader) | Public, unauthenticated | Read-only; URLs built from config and tagged providers only, never from request input; absolute URLs use `base_url` (or the request host) through `OriginUrlGuard`. |
| Twig `nowo_seo_head()` / `nowo_seo_metadata()` | Every page that renders them | Values are escaped by Twig; JSON-LD is encoded with `<` / `>` escaped so it cannot break out of `<script>`. |
| Admin UI `/settings/seo`, `/admin/seo/surfaces` (`admin.enabled`, off by default) | Session-authenticated | `admin.role` (default `ROLE_ADMIN`) on every action; Symfony Forms with CSRF; canonical overrides restricted to the site origin. |
| JSON pencil API `GET/POST /_nowo/seo/surfaces/{key}/{locale}` (`admin.api_enabled`) | Session-authenticated | `admin.role`; `POST` requires `Content-Type: application/json` **and** an `X-CSRF-Token` header (see below); payload goes through the same validated `SeoSurfaceType`. |
| `nowo:seo:audit` console command | Operators only | Read-only report over tagged audit subjects. |
| Doctrine entities (`persistence.enabled`, off by default) | Database | Bundle-owned tables (optionally prefixed with `doctrine.table_prefix`); no raw SQL. |

Threats considered: SEO spam / open redirect through canonical overrides (blocked by the origin guard), stored XSS through SEO fields (Twig escaping + JSON-LD encoding), cross-site request forgery against the admin UI and the JSON API (Form CSRF + header token), host-header poisoning of absolute URLs (set `base_url` in production). Out of scope: authentication itself (the host firewall) and the content of host-provided providers.

## CSRF on session mutations (REQ-SEC-005)

- Admin forms (`SeoSiteSettingsType`, `SeoSurfaceType`) keep Symfony Form CSRF enabled.
- The JSON API `POST` requires a token in the `X-CSRF-Token` header (token id `nowo_seo_kit_surface_api`); `GET` on the same URL returns it as `csrf_token`. Validation is fail-closed: without a CSRF token manager (`symfony/security-csrf` + `framework.csrf_protection`) every `POST` is refused with 403.
- `admin.api_csrf_protection: false` disables the header check. Use it only when the API sits behind a Bearer / API-token firewall without session cookies, and document it in the host.

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
| **Permissions / exposure** | Routes and admin features documented; roles configured for production. |
| **Limits / DoS** | Sitemap/robots generation stays bounded; no unbounded crawl of untrusted URLs. |
| **CSRF** | Admin forms keep CSRF; JSON API writes require `X-CSRF-Token` (REQ-SEC-005). |
| **AI security audit** | Grade recorded in org `BUNDLES_SECURITY_ANALYSIS.md` when applicable (REQ-SEC-004); re-audit after changes to the admin UI or JSON API. |

Record confirmation in the release PR or tag notes.


## AI security audit (REQ-SEC-004)

| Field | Value |
|-------|--------|
| Date | 2026-07-29 |
| Method | Cursor / Nowo campaign static AI security review |
| Grade | **Pass (good)** |
| Overall risk | **Low** |
| Open Critical / High / Medium | None |
| Residual | JSON-LD may use Twig `|raw` for structured data; no UGC in SEO config by default — keep overrides trusted |
| Notes | Docs/checklist alignment; no auth surface beyond host routes |
