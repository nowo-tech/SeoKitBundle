# Spec-driven development

## Table of contents

- [Layers in sync](#layers-in-sync)
- [User stories](#user-stories)
- [Bundle functional scope](#bundle-functional-scope)
- [Validating the functional spec](#validating-the-functional-spec)
- [Requirement identifiers (REQ-*)](#requirement-identifiers-req-)
- [Contributor workflow](#contributor-workflow)
- [Relationship to Engram](#relationship-to-engram)
- [GitHub Spec Kit](#github-spec-kit)
- [See also](#see-also)

## Layers in sync

SeoKitBundle keeps three layers in sync:

1. **GitHub Spec Kit baseline** — [`specs/001-baseline/`](../specs/001-baseline/) ([`spec.md`](../specs/001-baseline/spec.md) for user stories, `FR-*` and `SC-*`; [`code-inventory.md`](../specs/001-baseline/code-inventory.md) for 100% `src/` traceability). Operator manual: [`SPEC-KIT.md`](SPEC-KIT.md).
2. **Product behavior** — what consuming applications may rely on, documented in [`CONFIGURATION.md`](CONFIGURATION.md), [`USAGE.md`](USAGE.md) and [`INSTALLATION.md`](INSTALLATION.md), and proven by tests under `tests/`.
3. **Traceability anchors** — stable `REQ-*` identifiers in Makefiles and demos so PRs and issues can cite tooling behavior.

PHPUnit and PHPStan are the mechanical proof alongside this prose; there is no separate executable spec language (no Gherkin).

## User stories

| ID | Story | Functional scope | Integrator docs |
| --- | --- | --- | --- |
| US-01 | Render SEO head tags (title, meta, Open Graph, Twitter, canonical, hreflang, JSON-LD) with `{{ nowo_seo_head() }}` | FR-SEO-002, FR-SEO-003, FR-SEO-006, FR-SEO-012 | [USAGE.md](USAGE.md) |
| US-02 | Multilingual paths and translated slugs drive hreflang and sitemap URLs | FR-SEO-001, FR-SEO-004 | [CONFIGURATION.md](CONFIGURATION.md) |
| US-03 | `/sitemap.xml` and `/robots.txt` served by Symfony routes (extra robots groups, GEO companion) | FR-SEO-005, FR-SEO-007, FR-SEO-008 | [USAGE.md](USAGE.md), [SERVERS.md](SERVERS.md) |
| US-04 | Per-request overrides via `SeoRuntime` or the `#[Seo]` controller attribute | FR-SEO-002 | [USAGE.md](USAGE.md) |
| US-05 | Typed, CSP-safe JSON-LD nodes and site structured data | FR-SEO-009, FR-SEO-011 | [USAGE.md](USAGE.md) |

The acceptance scenarios (Given/When/Then) live in [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md).

## Bundle functional scope

**Goal:** hierarchical SEO configuration (defaults → slug_routes → pages → slugs → attribute → runtime), Twig head rendering, hreflang alternates, sitemap and robots endpoints that work on FrankenPHP (classic and worker), php-fpm and Nginx.

**In scope:**

- `nowo_seo_kit` configuration tree, metadata resolver and template placeholders.
- Twig functions `nowo_seo_head()`, `nowo_seo_metadata()`, `nowo_seo_enabled()` and the overridable `seo/head.html.twig` partial.
- Sitemap / robots controllers and routes, optional static route loader.
- Typed JSON-LD model and the optional PageHead pipeline for CMS hosts.
- Optional Doctrine persistence (site settings, surfaces) with the optional SEO admin and the `nowo:seo:audit` command.

**Explicit non-goals:**

- Search Console / analytics integration.
- Dynamic slug discovery from the database (only configured slugs appear in the sitemap).
- `/llms.txt`, citation index and the default AI crawler catalog (companion `nowo-tech/generative-seo-kit-bundle`).

**Not part of the Packagist API:** `demo/symfony8` and its FrankenPHP setup are illustrative only.

## Validating the functional spec

```bash
make test              # PHPUnit (Docker)
make phpstan           # PHPStan incl. FrankenPHP rulesets
make cs-check          # PHP-CS-Fixer (dry run)
make release-check     # full pre-release gate
```

Every behavior change requires tests under `tests/`; a functional claim in the spec without a test is not accepted.

## Requirement identifiers (REQ-*)

| ID | Location | Meaning |
| --- | --- | --- |
| REQ-GIT-001 | `Makefile` (`check-no-cursor-coauthor`), CI `git-hygiene` job | No AI co-author trailers in history |
| REQ-MAKE-007 | `Makefile` (`down-dev`) | Stop containers without removing volumes |
| REQ-MAKE-008 | `Makefile` / `demo/Makefile` (`update-deps`, shared include) | Update Composer dependencies in bundle + demos |
| REQ-MAKE-010 | `Makefile`, `demo/symfony8/Makefile` (`COMPOSE` detection) | Prefer Compose V2, fall back to V1 |
| REQ-CS-008 | `Makefile` (`igor`) | Igor worker-state audit |
| REQ-DOCS-002 | `README.md` `## Documentation` | Canonical documentation list |

When scripted or demo behavior changes, update the comment next to the existing `REQ-*` or add a new ID and cite it in the PR.

## Contributor workflow

1. Clarify the behavior in the baseline spec (or a new `specs/00N-*` feature folder).
2. Implement with tests (`make test`, `make phpstan`).
3. Anchor scripted behavior with a `REQ-*` comment when Makefiles or demos change.
4. Update `code-inventory.md` when production files are added, moved or removed (see the [maintainer checklist](SPEC-KIT.md#maintainer-checklist)).
5. Ship integrator docs (`CONFIGURATION.md`, `USAGE.md`), `CHANGELOG.md` and `UPGRADING.md` when behavior or config changes; validate with `make release-check`.

## Relationship to Engram

[`ENGRAM.md`](ENGRAM.md) covers the org-wide checklist plane (MCP memory for assistants, `.cursor/mcp.json`). It does not replace this document: this file and the Spec Kit baseline define what the bundle guarantees and how it is proven, while Engram persists decisions across sessions and points back here for local `REQ-*` traceability.

## GitHub Spec Kit

The repository is initialized with Specify CLI for **Cursor Agent** (`specify init --here --force --integration cursor-agent --script sh`). Skills live under `.cursor/skills/speckit-*`, infrastructure under `.specify/`, and the baseline under `specs/001-baseline/`. The full manual (install, structure, skills, checklist, troubleshooting) is [`SPEC-KIT.md`](SPEC-KIT.md). Keep the baseline inventory aligned with `src/` in every PR that touches production files.

## See also

- [GitHub Spec Kit manual](SPEC-KIT.md)
- [Baseline spec](../specs/001-baseline/spec.md) and [code inventory](../specs/001-baseline/code-inventory.md)
- [Usage](USAGE.md), [Configuration](CONFIGURATION.md), [Contributing](CONTRIBUTING.md), [Release](RELEASE.md)
- [Demo with FrankenPHP](DEMO-FRANKENPHP.md)
