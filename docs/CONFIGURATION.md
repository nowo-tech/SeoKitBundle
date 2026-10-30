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
- [doctrine](#doctrine)
- [web_ui](#web_ui)
  - [Host layout and CSS stack](#host-layout-and-css-stack)
- [security](#security)
  - [Host access_control](#host-access_control)
- [admin (deprecated)](#admin-deprecated)
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
| `x_robots_tag.enabled` | `false` | Send the page's resolved robots value as an `X-Robots-Tag` response header on every main response (HTML, JSON, files); a response that already has the header keeps it. |
| `indexable` | `true` | When false (or a `SiteIndexabilityProviderInterface` returns false), robots disallow `/` and sitemap is empty/404. Page meta robots is forced to `noindex,nofollow` (overriding any `robots` set by defaults, pages, slugs or providers) unless it already contains `noindex`. |
| `default_locale` | `en` | Fallback locale |
| `locales` | `en, es, fr, de, it, pt, nl` | Locales for hreflang and sitemap |
| `base_url` | `null` | Absolute origin when Request host unavailable (CLI, tests). Also switches on `AbsoluteUrlBuilder` and the PageHead pipeline (see [page_head](#page_head)); set it whenever `persistence` / `web_ui` are enabled (see [persistence](#persistence)). |

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

## doctrine

| Key | Default | Description |
| --- | --- | --- |
| `table_prefix` | `''` | Prepended to every SeoKit table and named unique constraint (REQ-ORM-002). Letters, digits and underscores only. Empty keeps the names above. Applied by a `loadClassMetadata` listener that only touches `Nowo\SeoKitBundle\Entity\*` and is registered only when persistence is enabled and the prefix is not empty. |

```yaml
nowo_seo_kit:
    persistence:
        enabled: true
    doctrine:
        table_prefix: 'app_'   # app_nowo_seo_site_settings, app_nowo_seo_site_settings_translation, app_nowo_seo_surface
```

Changing the prefix renames the tables: generate a Doctrine migration (`doctrine:migrations:diff`) and review the `RENAME TABLE` statements before deploying.

Typed JSON-LD helpers live under `Nowo\SeoKitBundle\Model\StructuredData\` (see [USAGE.md](USAGE.md) / factory `SiteStructuredDataFactory`).

## web_ui

Admin UI (site settings, surfaces list + edit) and the JSON pencil API, on the **UiKit page contract** (REQ-UI-001 /
REQ-UI-003). Requires `persistence.enabled: true` (and, in practice, `base_url` — see [persistence](#persistence)),
`nowo-tech/ui-kit-bundle` and `nowo-tech/form-kit-bundle` (both required by the package; register their bundles), and
Symfony Security unless `security.allow_unauthenticated` is true. Import the routes with `type: nowo_seo_kit_admin`;
only that loader honours the toggles below (the legacy `@NowoSeoKitBundle/Resources/config/routes_admin.yaml` file is
deprecated and ignores them).

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `false` | Register the admin pages and the API (routes, controllers, forms, access checker) |
| `settings` | `true` | Register the site settings page |
| `surfaces` | `true` | Register the surfaces list + edit pages |
| `path_prefix` | `null` | URL prefix of the admin pages: `<prefix>/settings`, `<prefix>/surfaces`, `<prefix>/surfaces/{locale}/edit/{surfaceKey}`. `null` keeps the 1.x paths `/settings/seo` and `/admin/seo/surfaces…` (the 2.0 default will be `/admin/seo`). Must start with `/`, URL path segments only, no `.` / `..` segment. Do not also import the deprecated `routes_admin.yaml`: it re-registers the 1.x paths under the same route names. The API stays under `/_nowo/seo/surfaces`. |
| `layout_template` | `null` | Layout the page scaffolds extend. `null` = the UiKit admin layout (`nowo_ui_kit.layouts.admin`). Set it to **your project layout** (or a one-file bridge) when it renders the UiKit canonical blocks; see [Host layout and CSS stack](#host-layout-and-css-stack). |
| `templates.settings` | `@NowoSeoKitBundle/admin/settings.html.twig` | Per-page override (extend the bundle page, override a data block — [TEMPLATES.md](TEMPLATES.md#per-page-overrides)) |
| `templates.surfaces_index` | `@NowoSeoKitBundle/admin/surfaces_index.html.twig` | idem |
| `templates.surfaces_form` | `@NowoSeoKitBundle/admin/surfaces_form.html.twig` | idem |
| `form_themes` | `['@NowoUiKit/form/nowo_ui_layout.html.twig']` | Form themes applied to the admin forms (the UiKit form layout reads the theme slots, so it follows `nowo_ui_kit.css_framework`) |
| `api_enabled` | `true` | `GET/POST /_nowo/seo/surfaces/{key}/{locale}`. `POST` only accepts a JSON object with `Content-Type: application/json` (415 otherwise). Locales that are not enabled and keys longer than 180 characters answer 404. |
| `api_csrf_protection` | `true` | `POST` also requires an `X-CSRF-Token` header (token id `nowo_seo_kit_surface_api`, returned as `csrf_token` by `GET`); missing/invalid token or no CSRF manager → 403. Set `false` only behind a Bearer / API-token firewall without session cookies (a production setup, so it is not tied to `kernel.debug`; `POST` still requires `Content-Type: application/json`, which a cross-site form cannot send without a CORS preflight). Literal boolean only. |

The container refuses to compile with `web_ui.enabled` when form CSRF is off or missing (`CsrfProtectionPass`) and,
unless `security.allow_unauthenticated` is true, without SecurityBundle (`WebUiSecurityPass`).

### Host layout and CSS stack

The pages extend the UiKit scaffolds (`@NowoUiKit/page/form.html.twig`, `@NowoUiKit/page/list.html.twig`); chrome,
CSS framework, icons, row-action display and design tokens come from **UiKit**, configured once for every Nowo bundle:

```yaml
# config/packages/nowo_ui_kit.yaml — once per application
nowo_ui_kit:
    css_framework: bootstrap5     # bootstrap (= bootstrap5) | bootstrap4 | tailwind | foundation | tabler | custom | none
    icon_set: bootstrap-icons
    row_actions_display: icon     # icon | text | icon_text
    layouts:
        admin: 'admin/layout.html.twig'   # your layout or a one-file bridge (default @NowoUiKit/layout/admin.html.twig)
```

| Host stack | `nowo_ui_kit.css_framework` | Notes |
| --- | --- | --- |
| Bootstrap 5 (or Tabler) | `bootstrap5` / `tabler` | Theme slots add Bootstrap classes (`btn btn-primary`, `table`, `form-control`); load Bootstrap in your layout |
| Tailwind | `tailwind` | Theme slots add Tailwind utilities; include the UiKit templates in your Tailwind `content` globs |
| Foundation | `foundation` | Foundation classes through the slots |
| Own styles | `custom` | Only the semantic `nowo-ui-*` classes; style them (or remap the `--nowo-ui-*` tokens) in your CSS |

Use `nowo_seo_kit.web_ui.layout_template` only when the SEO pages need a different layout than the rest of your
admin. A layout must render the UiKit canonical blocks (`nowo_ui_document_title`, `nowo_ui_head`, `nowo_ui_styles`,
`nowo_ui_page_header`, `nowo_ui_flashes`, `nowo_ui_content`, `nowo_ui_modals`, `nowo_ui_scripts`); a project layout
with other block names takes a one-file bridge:

```twig
{# templates/admin/seo_bridge.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}{% block nowo_ui_document_title %}{% endblock %}{% endblock %}

{% block stylesheets %}
    {{ parent() }}
    <link rel="stylesheet" href="{{ asset('css/nowo-ui.css', 'nowo_ui_kit') }}">
    {% block nowo_ui_head %}{% endblock %}
    {% block nowo_ui_styles %}{% endblock %}
{% endblock %}

{% block body %}
    {% block nowo_ui_page_header %}{% endblock %}
    {% block nowo_ui_flashes %}{{ include(nowo_ui_partial('flashes')) }}{% endblock %}
    {% block nowo_ui_content %}{% endblock %}
    {% block nowo_ui_modals %}{% endblock %}
{% endblock %}

{% block javascripts %}
    {{ parent() }}
    {% block nowo_ui_scripts %}{{ include(nowo_ui_partial('scripts')) }}{% endblock %}
{% endblock %}
```

The bundle ships no CSS and no JavaScript of its own: the stylesheet and the runtimes (`nowo-ui-tabs.js` for the
locale tabs, …) come from the UiKit asset package `nowo_ui_kit` (`assets:install`). The pages have no modals and no
delete action (an empty override form clears the stored row), so no confirm dialog is involved.

## security

Access to the admin pages and the JSON API (REQ-UI-002). Every action goes through
`Nowo\SeoKitBundle\Security\SeoKitAccessCheckerInterface`; a refusal is an `AccessDeniedException` (anonymous users
reach the firewall entry point, authenticated users get 403).

| Key | Default | Description |
| --- | --- | --- |
| `access_roles` | `['ROLE_ADMIN']` | The user needs **at least one** of these roles (default checker `ConfigurableSeoKitAccessChecker`). `PUBLIC_ACCESS` / `IS_AUTHENTICATED_ANONYMOUSLY` / blank entries and `%env()%` placeholders are rejected. `IS_AUTHENTICATED*` / `ROLE_USER` are accepted but open the admin to **every logged-in user** ([SECURITY.md](SECURITY.md#roles-that-open-the-admin-to-every-account)). An empty list is refused while `web_ui.enabled` unless `access_checker` is set (the default checker would deny everybody: fail closed). |
| `access_checker` | `null` | Service id implementing `SeoKitAccessCheckerInterface` (teams, voters, …). The interface is aliased to it. |
| `allow_unauthenticated` | `false` | **DEV / DEMO only.** The admin UI and the API become public (`AllowAllSeoKitAccessChecker`) and SecurityBundle is not required. The container refuses to compile with `true` unless `kernel.debug` is true, and always in the `prod` environment (`WebUiSecurityPass`). Literal boolean only: an `%env()%` placeholder is refused (as for `web_ui.enabled` and `web_ui.api_csrf_protection`). |

```yaml
nowo_seo_kit:
    persistence:
        enabled: true
    web_ui:
        enabled: true
        path_prefix: /admin/seo
    security:
        access_roles: [ROLE_ADMIN, ROLE_SEO]
        # access_checker: App\Security\SeoAccessChecker
        allow_unauthenticated: false
```

### Host access_control

Keep the firewall in front of the admin paths too (defense in depth):

```yaml
# config/packages/security.yaml (host application)
security:
    access_control:
        - { path: ^/admin/seo, roles: ROLE_ADMIN }       # web_ui.path_prefix (1.x paths: ^/settings/seo and ^/admin/seo/surfaces)
        - { path: ^/_nowo/seo/, roles: ROLE_ADMIN }      # JSON pencil API
```

## admin (deprecated)

The 1.x `admin` keys still work (each one maps onto its replacement) and trigger a deprecation. A legacy key and its
replacement set to **different** values is a configuration error; `admin.role` must be a literal role (no
`%env()%` placeholder). They are removed in 2.0 — see [UPGRADING.md](UPGRADING.md#unreleased).

| Deprecated key | Replacement |
| --- | --- |
| `admin.enabled` | `web_ui.enabled` |
| `admin.settings` | `web_ui.settings` |
| `admin.surfaces` | `web_ui.surfaces` |
| `admin.role` (scalar) | `security.access_roles` (list; `PUBLIC_ACCESS` is refused) |
| `admin.settings_template` | `web_ui.templates.settings` |
| `admin.surfaces_index_template` | `web_ui.templates.surfaces_index` |
| `admin.surfaces_form_template` | `web_ui.templates.surfaces_form` |
| `admin.api_enabled` | `web_ui.api_enabled` |
| `admin.api_csrf_protection` | `web_ui.api_csrf_protection` |

## Web servers

SEO routes are served by Symfony. See [SERVERS.md](SERVERS.md) for FrankenPHP, Nginx + php-fpm, and caching notes.
