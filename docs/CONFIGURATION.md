# Configuration

Configuration root: `nowo_seo_kit` (alias `nowo_seo_kit`).

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
| `default_locale` | `en` | Fallback locale |
| `locales` | `en, es, fr, de, it, pt, nl` | Locales for hreflang and sitemap |
| `base_url` | `null` | Absolute origin when Request host unavailable (CLI, tests) |

## defaults

| Key | Default | Description |
| --- | --- | --- |
| `site_name` | `''` | Appended via `title_template` |
| `title_separator` | ` \| ` | Between title and site name |
| `title_template` | `{title}{separator}{site_name}` | Final title format |
| `canonical_enabled` | `true` | Emit canonical link |
| `hreflang_enabled` | `true` | Emit alternate links |
| `x_default_hreflang` | `true` | Add `x-default` hreflang |

Nested `open_graph`, `twitter`, and `json_ld` blocks have sensible defaults (see `Configuration.php`).

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

Override in your app: `templates/bundles/NowoSeoKitBundle/seo/head.html.twig`.

## Web servers

SEO routes are served by Symfony. See [SERVERS.md](SERVERS.md) for FrankenPHP, Nginx + php-fpm, and caching notes.
