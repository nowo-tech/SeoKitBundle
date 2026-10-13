# Configuration

Configuration root: `nowo_seo_kit` (alias `nowo_seo_kit`).

## Table of contents

- [Resolution order](#resolution-order)
- [Top-level keys](#top-level-keys)
- [Host extension points](#host-extension-points)
- [defaults](#defaults)
- [pages](#pages)
- [slug_routes](#slug_routes)
- [slugs](#slugs)
- [sitemap](#sitemap)
- [robots](#robots)
- [templates](#templates)
- [page_head](#page_head)
- [persistence](#persistence)
- [admin](#admin)
- [Web servers](#web-servers)

## Resolution order

Later layers override earlier ones:

1. `defaults` — global site name, title template, Open Graph / Twitter / JSON-LD defaults
2. `slug_routes.<route>` — templates and path patterns for slug-based routes
3. `pages.<route>` — static route SEO (with optional `locales` overrides)
4. `slugs.<route>.<slug>` — per-slug overrides (including translated slugs and paths)
5. `#[Seo]` attribute on controller class or action
6. `SeoRuntime` — request-scoped overrides from PHP

## Top-level keys

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `true` | Master switch |
| `indexable` | `true` | When false (or a `SiteIndexabilityProviderInterface` returns false), robots disallow `/` and sitemap is empty/404. Page meta robots is forced to `noindex,nofollow` (overriding any `robots` set by defaults, pages, slugs or providers) unless it already contains `noindex`. |
| `default_locale` | `en` | Fallback locale |
| `locales` | `en, es, fr, de, it, pt, nl` | Locales for hreflang and sitemap |
| `base_url` | `null` | Absolute origin when Request host unavailable (CLI, tests). Also switches on `AbsoluteUrlBuilder` and the PageHead pipeline (see [page_head](#page_head)); set it whenever `persistence` / `admin` are enabled (see [persistence](#persistence)). |

## Host extension points

| Tag / interface | Purpose |
| --- | --- |
| `nowo_seo_kit.defaults_provider` (`SeoDefaultsProviderInterface`) | Merge DB-driven site defaults (name, verification, OG image, organization JSON-LD) |
| `nowo_seo_kit.indexability_provider` (`SiteIndexabilityProviderInterface`) | Site-wide indexability master switch |
| `nowo_seo_kit.sitemap_url_provider` (`SitemapUrlProviderInterface`) | CMS / blog absolute URLs (optional `xhtml:link` alternates) |
| `nowo_seo_kit.audit_subject_provider` (`SeoAuditSubjectProviderInterface`) | Subjects for `nowo:seo:audit` |
| `nowo_seo_kit.geo_robots_groups_provider` (`GeoRobotsGroupsProviderInterface`) | Extra robots.txt User-agent groups (omitted when not indexable). [`GenerativeSeoKitBundle`](https://github.com/nowo-tech/GenerativeSeoKitBundle) implements this tag. |

Runtime overrides may set `title_final`, `alternates`, and `json_ld.json` (pre-encoded safe JSON-LD).

`templates.head_includes_title` (default `true`): set `false` when the host layout owns the `<title>` block.

## defaults

| Key | Default | Description |
| --- | --- | --- |
| `site_name` | `''` | Appended via `title_template` |
| `title_separator` | ` \| ` | Between title and site name |
| `title_template` | `{title}{separator}{site_name}` | Final title format |
| `canonical_enabled` | `true` | Emit canonical link |
| `hreflang_enabled` | `true` | Emit alternate links |
| `x_default_hreflang` | `true` | Add `x-default` hreflang |
| `verification.google` | `null` | Google Search Console meta content |
| `verification.bing` | `null` | Bing Webmaster `msvalidate.01` content |

Nested `open_graph` (includes `image`, `image_width`, `image_height`, `image_alt`, `locale_alternates`), `twitter`, and `json_ld` blocks have sensible defaults (see `Configuration.php`).

## pages

Keyed by Symfony **route name**. Supports:

- `title`, `description`, `robots`, `canonical`, `keywords`, `author`
- `path` — public path for default locale (sitemap / hreflang)
- `controller` — optional controller for static route loader
- `in_sitemap`, `sitemap_priority`, `sitemap_changefreq` (pages / slugs whose `robots` contains `noindex`, or slugs with `noindex: true`, are never listed in the sitemap)
- `locales.<locale>` — per-locale title, description, path, etc.

Example:

```yaml
nowo_seo_kit:
    pages:
        app_home:
            title: Home
            description: Welcome
            path: /
            locales:
                es:
                    title: Inicio
                    path: /es
```

## slug_routes

General rules for routes with a slug parameter:

| Key | Default | Description |
| --- | --- | --- |
| `slug_parameter` | `slug` | Request attribute name |
| `title_template` | — | e.g. `{title} — Blog` |
| `description_template` | — | Description with placeholders |
| `path_pattern` | — | e.g. `/blog/{slug}` or `/{locale}/blog/{slug}` |
| `locales.<locale>.path_pattern` | — | Locale-specific pattern |

## slugs

Specific slug values under a route. **Keys keep hyphens** (e.g. `my-post`); they are not normalized to underscores.

When the request slug is a translated value (`locales.<locale>.slug`), the resolver maps it back to the canonical key for metadata, hreflang, and sitemap path building.

```yaml
slugs:
    app_blog_show:
        my-post:
            title: Custom title
            noindex: false
            in_sitemap: true
            locales:
                es:
                    title: Título
                    slug: mi-articulo
                    path: /es/blog/mi-articulo
```

## sitemap

| Key | Default |
| --- | --- |
| `enabled` | `true` |
| `path` | `/sitemap.xml` |
| `include_static_pages` | `true` |
| `include_configured_slugs` | `true` |

## robots

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `true` | |
| `path` | `/robots.txt` | |
| `user_agent` | `*` | Default group |
| `allow` | `['/']` | Default group |
| `disallow` | `[]` | Default group |
| `sitemap_link` | `true` | |
| `groups` | `[]` | Extra `{user_agent, allow, disallow}` blocks after the default group when indexable. Prefer [`nowo-tech/generative-seo-kit-bundle`](https://packagist.org/packages/nowo-tech/generative-seo-kit-bundle) (`GeoRobotsGroupsProviderInterface`) for AI crawlers; do not duplicate the same User-agent in YAML. |

## templates

| Key | Default |
| --- | --- |
| `head` | `@NowoSeoKitBundle/seo/head.html.twig` |
| `head_includes_title` | `true` |

Set `templates.head` to another logical Twig path to swap the head partial without a full-file vendor override. Full-file overrides under `templates/bundles/NowoSeoKitBundle/` (REQ-TWIG-001): [USAGE.md — Overriding templates](USAGE.md#overriding-templates-req-twig-001).

## page_head

Layered PageHead resolution for CMS hosts (`PageHeadResolver`, `PageHeadContext`, `ConfigPageHeadDefaultsProvider`).
**These services — and therefore every `page_head.*` key — are only registered when `base_url` is set**; without it the keys are accepted but have no effect.

| Key | Default | Description |
| --- | --- | --- |
| `open_graph_regions` | `{es: ES, ca: ES, en: GB, pt: PT, fr: FR, it: IT}` | Map locale → region used to build `og:locale` (`<locale>_<REGION>`). Locales missing from the map fall back to the upper-cased locale (`de` → `de_DE`). Setting the key replaces the whole map. |

```yaml
nowo_seo_kit:
    base_url: 'https://example.com'
    page_head:
        open_graph_regions:
            en: US
            es: MX
            de: AT
```

## persistence

Opt-in Doctrine site settings (requires `doctrine/orm` + `doctrine/doctrine-bundle`).

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `false` | Register entities, repositories, `SeoSiteConfigProvider`, `DoctrineSeoDefaultsProvider` |
| `fallback_robots` | `index, follow` | Used when DB row missing |
| `fallback_site_name` | `''` | Used when DB row missing |
| `fallback_contact_email` | `''` | Used when DB row missing |
| `register_audit_command` | `true` | Register `nowo:seo:audit` |

Tables: `nowo_seo_site_settings`, `nowo_seo_site_settings_translation`, `nowo_seo_surface`.

**Set `base_url` together with persistence (and admin).** Without it:

- the PageHead pipeline (`PageHeadResolver` / `PageHeadContext` / `AbsoluteUrlBuilder`) that CMS hosts use to render persisted surface overrides (canonical, hreflang, Open Graph URLs) is not registered;
- `OriginUrlGuard` has no origin, so admin/API canonical overrides are only accepted as site-relative paths (`/path`) — absolute URLs, even on your own host, are rejected.

Typed JSON-LD helpers live under `Nowo\SeoKitBundle\Model\StructuredData\` (see [USAGE.md](USAGE.md) / factory `SiteStructuredDataFactory`).

## admin

Requires `persistence.enabled: true` (and, in practice, `base_url` — see [persistence](#persistence)). Import routes with `type: nowo_seo_kit_admin`; only that loader honours the `enabled` / `settings` / `surfaces` / `api_enabled` toggles below. The legacy `@NowoSeoKitBundle/Resources/config/routes_admin.yaml` file is deprecated and ignores them.

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `false` | Master switch for admin + API loaders |
| `settings` | `true` | Register `/settings/seo` |
| `surfaces` | `true` | Register `/admin/seo/surfaces` |
| `api_enabled` | `true` | `GET/POST /_nowo/seo/surfaces/{key}/{locale}`. `POST` only accepts a JSON object with `Content-Type: application/json` (415 otherwise); no CSRF token is needed because cross-site forms cannot send JSON without a CORS preflight. |
| `role` | `ROLE_ADMIN` | Access control |
| `settings_template` / `surfaces_*_template` | kit defaults | Override with host Twig paths |

## Web servers

SEO routes are served by Symfony. See [SERVERS.md](SERVERS.md) for FrankenPHP, Nginx + php-fpm, and caching notes.
