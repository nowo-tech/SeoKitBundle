# Security

## Table of contents

- [Reporting vulnerabilities](#reporting-vulnerabilities)
- [Integrator guidance](#integrator-guidance)
- [Dependencies](#dependencies)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)

## Reporting vulnerabilities

Report security issues privately to **hectorfranco@nowo.tech**. Do not open public issues for sensitive reports.

See [.github/SECURITY.md](../.github/SECURITY.md) for supported versions.

## Integrator guidance

- Set `base_url` in production when generating canonicals/sitemap behind proxies or in CLI.
- Use `noindex` on slugs or runtime overrides for private or draft content.
- Keep `robots.disallow` aligned with admin and API paths.
- Do not expose internal hostnames in `base_url` or canonical overrides.
- Escape/encode user-controlled SEO fields in Twig (`title`, `description`, Open Graph values).

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
| **AI security audit** | Grade recorded in org `BUNDLES_SECURITY_ANALYSIS.md` when applicable (REQ-SEC-004). |

Record confirmation in the release PR or tag notes.
