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
| `indexable` | `true` | When false (or a `SiteIndexabilityProviderInterface` returns false), robots disallow `/` and sitemap is empty/404 |
| `default_locale` | `en` | Fallback locale |
| `locales` | `en, es, fr, de, it, pt, nl` | Locales for hreflang and sitemap |
| `base_url` | `null` | Absolute origin when Request host unavailable (CLI, tests) |

## Host extension points

| Tag / interface | Purpose |
| --- | --- |
| `nowo_seo_kit.defaults_provider` (`SeoDefaultsProviderInterface`) | Merge DB-driven site defaults (name, verification, OG image, organization JSON-LD) |
| `nowo_seo_kit.indexability_provider` (`SiteIndexabilityProviderInterface`) | Site-wide indexability master switch |
| `nowo_seo_kit.sitemap_url_provider` (`SitemapUrlProviderInterface`) | CMS / blog absolute URLs (optional `xhtml:link` alternates) |
| `nowo_seo_kit.audit_subject_provider` (`SeoAuditSubjectProviderInterface`) | Subjects for `nowo:seo:audit` |

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
- `in_sitemap`, `sitemap_priority`, `sitemap_changefreq`
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

| Key | Default |
| --- | --- |
| `enabled` | `true` |
| `path` | `/robots.txt` |
| `user_agent` | `*` |
| `allow` | `['/']` |
| `disallow` | `[]` |
| `sitemap_link` | `true` |

## templates

| Key | Default |
| --- | --- |
| `head` | `@NowoSeoKitBundle/seo/head.html.twig` |
| `head_includes_title` | `true` |

Set `templates.head` to another logical Twig path to swap the head partial without a full-file vendor override. Full-file overrides under `templates/bundles/NowoSeoKitBundle/` (REQ-TWIG-001): [USAGE.md — Overriding templates](USAGE.md#overriding-templates-req-twig-001).

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

Typed JSON-LD helpers live under `Nowo\SeoKitBundle\Model\StructuredData\` (see [USAGE.md](USAGE.md) / factory `SiteStructuredDataFactory`).

## admin

Requires `persistence.enabled: true`. Import routes with `type: nowo_seo_kit_admin`.

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `false` | Master switch for admin + API loaders |
| `settings` | `true` | Register `/settings/seo` |
| `surfaces` | `true` | Register `/admin/seo/surfaces` |
| `api_enabled` | `true` | `GET/POST /_nowo/seo/surfaces/{key}/{locale}` |
| `role` | `ROLE_ADMIN` | Access control |
| `settings_template` / `surfaces_*_template` | kit defaults | Override with host Twig paths |

## Web servers

SEO routes are served by Symfony. See [SERVERS.md](SERVERS.md) for FrankenPHP, Nginx + php-fpm, and caching notes.
