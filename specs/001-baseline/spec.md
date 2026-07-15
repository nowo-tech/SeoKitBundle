# Feature Specification: SeoKitBundle baseline

**Feature Branch**: `001-baseline`  
**Created**: 2026-07-16  
**Status**: Active  
**Input**: Baseline specification for multilingual SEO kit (meta, hreflang, sitemap, robots).

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md)  
**Code inventory**: [`code-inventory.md`](code-inventory.md)

---

## Summary

**Package**: `nowo-tech/seo-kit-bundle`  
**Configuration root**: `nowo_seo_kit`

Symfony bundle providing hierarchical SEO configuration (defaults → slug_routes → pages → slugs → attribute → runtime), Twig head rendering, hreflang alternates, sitemap.xml and robots.txt endpoints compatible with FrankenPHP, php-fpm, and Nginx.

---

## User Scenarios & Testing

### User Story 1 — Render SEO head tags (Priority: P1)

As an integrator, I add `{{ nowo_seo_head() }}` to my base layout and get title, meta, Open Graph, Twitter, canonical, hreflang, and JSON-LD for the current request.

**Independent Test**: Configure `pages.app_home` and request `/` → head contains title and canonical.

**Acceptance Scenarios**:

1. **Given** `enabled: true` and page config, **When** resolver runs, **Then** merged metadata uses layer order with later layers winning.
2. **Given** `enabled: false` or no request, **When** resolver runs, **Then** empty/disabled metadata is returned.

---

### User Story 2 — Multilingual paths and hreflang (Priority: P1)

As an integrator, I define locale-specific paths under `pages` and translated slugs under `slugs` so hreflang and sitemap URLs match public URLs.

**Acceptance Scenarios**:

1. **Given** `pages.home.locales.es.path`, **When** building alternates, **Then** Spanish alternate uses `/es` path.
2. **Given** `slugs.blog.post.locales.es.slug`, **When** building slug path, **Then** translated slug replaces `{slug}` in pattern.

---

### User Story 3 — Sitemap and robots (Priority: P1)

As an integrator, I expose `/sitemap.xml` and `/robots.txt` via Symfony routes (no web-server special files required).

**Acceptance Scenarios**:

1. **Given** configured static pages and slugs, **When** sitemap is requested, **Then** XML lists absolute URLs per locale.
2. **Given** robots config, **When** robots.txt is requested, **Then** output includes Allow/Disallow and optional Sitemap line.

---

### User Story 4 — Runtime and attribute overrides (Priority: P2)

As a developer, I override SEO per request via `SeoRuntime` or `#[Seo]` on controllers.

**Acceptance Scenarios**:

1. **Given** runtime `set(['title' => 'X'])`, **When** head renders, **Then** runtime layer wins.
2. **Given** `#[Seo(noindex: true)]`, **When** resolver runs, **Then** robots is `noindex,nofollow`.

---

## Functional Requirements

| ID | Requirement |
| --- | --- |
| FR-SEO-001 | Configuration tree under `nowo_seo_kit` with defaults, pages, slug_routes, slugs, sitemap, robots, templates |
| FR-SEO-002 | `SeoMetadataResolver` merges layers in documented order |
| FR-SEO-003 | `SeoTemplateRenderer` replaces `{placeholder}` tokens |
| FR-SEO-004 | `SeoPathBuilder` builds absolute URLs, page paths, slug paths |
| FR-SEO-005 | `SitemapGenerator` and `RobotsTxtGenerator` produce standards-compliant output |
| FR-SEO-006 | Twig functions `nowo_seo_head()`, `nowo_seo_metadata()`, `nowo_seo_enabled()` |
| FR-SEO-007 | Routes for sitemap and robots; optional static route loader type `nowo_seo_kit` |
| FR-I18N-001 | Translation files with key parity across en, es, fr, de, it, pt, nl |

---

## Out of scope (baseline)

- Search Console / analytics integration
- Dynamic slug discovery from database (only configured slugs appear in sitemap)
