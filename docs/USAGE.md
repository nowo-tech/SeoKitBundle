# Usage

## Table of contents

- [Twig](#twig)
- [PHP attribute](#php-attribute)
- [Runtime overrides](#runtime-overrides)
- [Optional Doctrine persistence](#optional-doctrine-persistence)
- [Using it from a DDD / hexagonal application](#using-it-from-a-ddd--hexagonal-application)
- [Optional SEO admin](#optional-seo-admin)
- [Audit CLI](#audit-cli)
- [Typed JSON-LD](#typed-json-ld)
  - [Extra robots.txt groups](#extra-robotstxt-groups)
- [Sitemap and robots](#sitemap-and-robots)
- [Overriding templates (REQ-TWIG-001)](#overriding-templates-req-twig-001)
- [Translations](#translations)
- [Demo](#demo)
- [Dependency updates (maintainers)](#dependency-updates-maintainers)
- [Surface keys (1.8+)](#surface-keys-18)
- [Absolute URLs (1.8.1+)](#absolute-urls-181)
- [Brand name and logo (1.21+)](#brand-name-and-logo-121)

## Twig

```twig
{# base.html.twig #}
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{ nowo_seo_head() }}
</head>
```

**CSP nonce.** The JSON-LD `<script>` gets a `nonce` attribute when one is available: pass it explicitly
(`{{ nowo_seo_head(csp_nonce('script')) }}` with NelmioSecurityBundle, or any string) or let your CSP listener
store it in the request attribute `csp_nonce` (`$request->attributes->set('csp_nonce', $nonce)`), which
`nowo_seo_head()` reads when no argument is given.

Inspect resolved metadata in templates:

```twig
{% set seo = nowo_seo_metadata() %}
<h1>{{ seo.title }}</h1>
```

## PHP attribute

```php
use Nowo\SeoKitBundle\Attribute\Seo;

#[Seo(title: 'About us', description: 'Company information')]
final class AboutController
{
    // ...
}
```

## Runtime overrides

Inject `SeoRuntime` in a controller:

```php
public function show(SeoRuntime $seo, string $slug): Response
{
    $seo->set([
        'title' => 'Dynamic '.$slug,
        'description' => 'Loaded from database',
        'title_final' => true, // skip defaults.title_template wrapping
    ]);
    $seo->setVariables(['title' => 'Article title']);

    return $this->render('blog/show.html.twig');
}
```

Runtime overrides are cleared at the **start** of every main request and again on `kernel.terminate`
(`SeoRuntimeClearSubscriber`, priority 4096). Call `set()` / `setVariables()` from controllers or from
`kernel.request` listeners with priority **lower than 4096**. Safe under FrankenPHP worker mode without kernel reset —
see [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

Hosts may also implement tagged providers (`SeoDefaultsProviderInterface`, `SiteIndexabilityProviderInterface`, `SitemapUrlProviderInterface`, `SeoAuditSubjectProviderInterface`, `GeoRobotsGroupsProviderInterface`) — see [CONFIGURATION.md — Host extension points](CONFIGURATION.md#host-extension-points).

## Optional Doctrine persistence

Enable with `nowo_seo_kit.persistence.enabled: true` (requires Doctrine ORM). See [CONFIGURATION.md — persistence](CONFIGURATION.md#persistence) and [UPGRADING.md — To 1.6.0](UPGRADING.md#to-160).

## Using it from a DDD / hexagonal application

SeoKit is a **presentation** bundle (head tags, JSON-LD, sitemap, robots.txt) with an **optional** small model of
its own (site settings and per-surface overrides, `persistence.enabled: true`). It never references your entities:
no relations, no `resolve_target_entities`, no base class. Host objects are linked by string surface keys
(`SeoSurfaceKeys::page('pricing')`, `SeoSurfaceKeys::blog($id)`), so your domain stays free of the bundle.

**Feed it from your domain through ports** (all autoconfigured, implement them in your infrastructure layer):

- `SitemapUrlProviderInterface` — sitemap entries for your published aggregates.
- `SeoAuditSubjectProviderInterface` — pages for `nowo:seo:audit`.
- `SeoDefaultsProviderInterface`, `SiteIndexabilityProviderInterface`, `PageHeadDefaultsProviderInterface`,
  `PageHeadSiteGraphProviderInterface`, `PageHeadTitleComposerInterface` — defaults, indexability, site JSON-LD,
  title composition.
- `SeoPathBuilderInterface` — replace or decorate URL building (RoutingKitBundle does this).
- `SeoKitAccessCheckerInterface` — admin access (`nowo_seo_kit.security.access_checker`).
- Site name and logo can come from a `Nowo\Brand\Contract\BrandProviderInterface` service (`nowo_seo_kit.brand`).

```php
final class ProductSitemapProvider implements SitemapUrlProviderInterface
{
    public function __construct(
        private ProductCatalog $catalog,          // your domain/application port
        private UrlGeneratorInterface $urls,
    ) {}

    public function getEntries(Request $request): array
    {
        $entries = [];
        foreach ($this->catalog->publishedSlugs() as $slug) {
            $entries[] = ['loc' => $this->urls->generate('product_show', ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL)];
        }

        return $entries;
    }
}
```

**Reacting to changes (since 1.22).** After a write is flushed the bundle dispatches id-only events (scalars only,
with `actorId` = the security token's user identifier, or null, and `occurredAt`):
`Nowo\SeoKitBundle\Event\SeoSurfaceChangedEvent` (`surfaceKey`, `locale`, `surfaceId`, `deleted` when the override
was cleared) from the surfaces admin, the JSON API and `SeoSurfaceManagerInterface::saveOrClear()`, and
`Nowo\SeoKitBundle\Event\SeoSiteSettingsChangedEvent` from the settings admin (after the config provider refresh).
Use them to purge HTTP / CDN caches or update projections. They are sent on every successful save (also with
unchanged values). `getOrCreate()` stores an empty row without an event, so `saveOrClear()` of an all-empty override
may send `deleted: true` for a row no event announced: treat a delete of an unknown surface as a no-op. `actorId` is
the user identifier (often the login email).

**Describe a page with DTOs, not entities.** In controllers, pass values through `SeoRuntime::set([...])` or build
a `PageHeadInput` and call `PageHeadContext::describe()`; map your domain objects to these in the controller or a
presenter, never the other way round.

**Known limitations.**

- Surface overrides are only available as the bundle's Doctrine entity `SeoSurface` through
  `SeoSurfaceManagerInterface` (since 1.22; Doctrine-backed implementation `SeoSurfaceManager`); there is no storage
  port yet, so keep that call in an infrastructure adapter.
- The bundle does not apply surface overrides to the head by itself: read them in your adapter and pass the values to
  `SeoRuntime` / `PageHeadInput`.
- The admin UI requires `persistence.enabled: true`; the entities use the default entity manager.

## Optional SEO admin

Requires persistence and Symfony Security. Enable `nowo_seo_kit.web_ui.enabled: true` (optionally `web_ui.path_prefix: /admin/seo`), import routes with `type: nowo_seo_kit_admin`, then use the settings page (`/settings/seo`), the surfaces pages (`/admin/seo/surfaces`) and optionally `GET/POST /_nowo/seo/surfaces/{key}/{locale}`. See [CONFIGURATION.md — web_ui](CONFIGURATION.md#web_ui) and [CONFIGURATION.md — security](CONFIGURATION.md#security). The per-locale wording of the settings page renders as one tab per enabled locale (FormKit `locale_tabs`, UiKit renderer); see [TEMPLATES.md](TEMPLATES.md).

```yaml
# config/routes/nowo_seo_kit.yaml
nowo_seo_kit_admin:
    resource: .
    type: nowo_seo_kit_admin
```

The pages are on the **UiKit page contract** (REQ-UI-003): they extend the UiKit form / list scaffolds, so the host
integrates them by configuring UiKit once (`nowo_ui_kit.layouts.admin` = your project layout or a one-file bridge,
`nowo_ui_kit.css_framework` = `bootstrap5` / `tailwind` / `foundation` / `custom`, `row_actions_display`, theme
tokens) — **no page template to copy**. Stylesheets and scripts stack through the layout's `nowo_ui_styles` /
`nowo_ui_scripts` blocks (UiKit asset package `nowo_ui_kit`); the bundle adds no asset of its own. See
[CONFIGURATION.md — Host layout and CSS stack](CONFIGURATION.md#host-layout-and-css-stack) for the bridge and the
framework table, and [TEMPLATES.md](TEMPLATES.md) for the pages, view models and data blocks.

A host menu (DashboardMenu) mounts the admin navigation with `nowo_ui_area_nav('seo_kit')` (SEO settings | Page SEO;
nothing for users the admin access checker refuses);
the pages also show it as header tabs. The surfaces list has one row action (Edit, `edit` kind through the UiKit
`row_actions` partial, so `row_actions_display: icon | text | icon_text` applies); there is no delete action and no
modal (an empty override form clears the stored row).

Admin forms are built with **[FormKit](https://github.com/nowo-tech/FormKitBundle)** (`AbstractSeoFormType`, profile `seo_kit`). Register `Nowo\FormKitBundle\NowoFormKitBundle` (Flex recipe) so labels resolve from `NowoSeoKitBundle` translations.

**Forms (REQ-TWIG-003 / REQ-TWIG-005):** every admin and demo form (settings, surfaces, the demo SEO CRUD and its CSRF-only deletes) is a PHP Form Type created in the controller and rendered with `form_start`, the rendered-aware child loop `{% for child in form %}{% if not child.rendered %}{{ form_row(child) }}{% endif %}{% endfor %}` (custom markup for a few children first, then the loop) and `form_end`. Host overrides and demos must not reintroduce raw `<form>` / `<input>` / `<textarea>` / `<select>`; `make check-no-raw-html-form` (part of `make release-check`) fails on them, with no allowlist.

## Audit CLI

```bash
php bin/console nowo:seo:audit
```

Register subjects via `SeoAuditSubjectProviderInterface` (`nowo_seo_kit.audit_subject_provider`).

## Typed JSON-LD

Use `Nowo\SeoKitBundle\Model\StructuredData\*` nodes and `SiteStructuredDataFactory` to build CSP-safe graphs (`StructuredDataGraph::toJson()`).

Besides `OrganizationNode` / `WebPageNode` / `BreadcrumbListNode`, hosts can emit `LocalBusinessNode`, `PersonNode`, `FaqPageNode`, and `BlogPostingNode`. Pass schema.org `@type` values that match the real organisation; do not invent medical types or FAQ answers.

### Extra robots.txt groups

```yaml
nowo_seo_kit:
    robots:
        groups:
            - user_agent: GPTBot
              allow: ['/']
```

Tagged `GeoRobotsGroupsProviderInterface` implementations append after YAML groups. Both are skipped when `indexable` is false.

Do not copy GPTBot / Google-Extended groups here if you install [`nowo-tech/generative-seo-kit-bundle`](https://packagist.org/packages/nowo-tech/generative-seo-kit-bundle): that package already feeds this SPI (and adds `/llms.txt`). YAML `groups` remain for one-off User-agents the companion catalog does not cover.

## Sitemap and robots

After configuration, verify:

```bash
curl -s https://your-host/sitemap.xml | head
curl -s https://your-host/robots.txt
```

> **Deprecated route files.** `@NowoSeoKitBundle/Resources/config/routes.yaml` and `routes_admin.yaml` are deprecated since 1.14.0 and will be removed in 2.0: they hard-code `/sitemap.xml` / `/robots.txt` and every admin/API controller, ignoring the `sitemap.*`, `robots.*` and `admin.*` path/enable toggles. Import `type: nowo_seo_kit` (and `type: nowo_seo_kit_admin`) instead — see [INSTALLATION.md — Routes](INSTALLATION.md#routes).

## Overriding templates (REQ-TWIG-001)

The bundle registers the Twig namespace **`@NowoSeoKitBundle/`**. Application files under **`templates/bundles/NowoSeoKitBundle/`** **always win** over the copies inside the package (`TwigPathsPass` prepends the app override directory when present, then registers the bundle views path).

**Freeze rule:** a full-file override hides vendor updates for that `<subpath>` until you delete or manually merge it. Prefer pointing **`nowo_seo_kit.templates.head`** at your own template (see [CONFIGURATION.md — templates](CONFIGURATION.md#templates)) when you only need a different head markup without freezing the vendor file.

**Procedure**

1. Identify the `<subpath>` from the table below (path relative to `src/Resources/views/`).
2. Create in your application: `templates/bundles/NowoSeoKitBundle/<subpath>` (same relative path and filename).
3. Clear the cache in dev if needed: `php bin/console cache:clear`.

Example:

```text
templates/bundles/NowoSeoKitBundle/seo/head.html.twig
```

Logical name: `@NowoSeoKitBundle/seo/head.html.twig` (used by `nowo_seo_head()` via the `templates.head` config default).

**Overridable templates**

| Subpath | Purpose | Upgrade-safe alternative |
| --- | --- | --- |
| `seo/head.html.twig` | HTML head tags rendered by `nowo_seo_head()` (title, meta, Open Graph, Twitter, JSON-LD, alternates) | `nowo_seo_kit.templates.head` |
| `admin/settings.html.twig` | Site settings page (UiKit form scaffold) | `nowo_seo_kit.web_ui.templates.settings` (extend `@!NowoSeoKitBundle/admin/settings.html.twig`, override a data block) |
| `admin/surfaces_index.html.twig` | Surfaces list (UiKit list scaffold) | `nowo_seo_kit.web_ui.templates.surfaces_index` |
| `admin/surfaces_form.html.twig` | Surface override form (UiKit form scaffold) | `nowo_seo_kit.web_ui.templates.surfaces_form` |

For the admin pages prefer theming UiKit once (`nowo_ui_kit.theme`, scaffolds under
`templates/bundles/NowoUiKitBundle/page/`) over any SeoKit override: see [TEMPLATES.md](TEMPLATES.md).

## Translations

UI fallback strings live in `NowoSeoKitBundle` domain YAML files. Override in `translations/NowoSeoKitBundle.en.yaml` in your app.

## Demo

```bash
make -C demo up-symfony8
```

Open the URL printed by the Makefile (default port from `demo/symfony8/.env.example`).

- Home without locale prefix: `http://localhost:8050/` (default locale)
- Locale switch in the layout (uses SEO alternates)
- SEO Admin CRUD: `http://localhost:8050/admin/seo`

See [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).

## Dependency updates (maintainers)

```bash
make update-deps   # bundle + demos (REQ-MAKE-008)
```

## Surface keys (1.8+)

Use conventional keys so admin, API pencil and host stores agree:

```php
use Nowo\SeoKitBundle\Service\SeoSurfaceKeys;
use Nowo\SeoKitBundle\Service\SeoSurfaceManagerInterface; // since 1.22; inject it ($surfaceManager)

$key = SeoSurfaceKeys::page('product.insurance_core'); // page:product.insurance_core
$surface = $surfaceManager->getOrCreate($key, 'es');
$surface->setMetaTitle('…');
$surfaceManager->saveOrClear($surface); // deletes the row when every field is empty
```

Requires `persistence.enabled: true` (registers `SeoSurfaceManager`, aliased as `SeoSurfaceManagerInterface`).

## Absolute URLs (1.8.1+)

When `base_url` is configured, `AbsoluteUrlBuilder` **and** the PageHead pipeline are registered. YAML `nowo_seo_kit.defaults` is mapped by `ConfigPageHeadDefaultsProvider` so companion bundles (for example GenerativeSeoKit) can set `base_url` without a custom `PageHeadDefaultsProviderInterface`. Override the interface alias in the host if you need locale-specific or Doctrine-backed PageHead defaults.

```php
use Nowo\SeoKitBundle\Service\AbsoluteUrlBuilder;

$canonical = $urls->fromPath('/producto/core'); // https://example.test/producto/core
$urls->belongsToSite($canonical); // true
```

Prefer this over a host-only canonical helper so origin + path shape stay consistent with sitemap / robots / head.

## Brand name and logo (1.21+)

With `nowo-tech/brand-contracts` installed and a `Nowo\Brand\Contract\BrandProviderInterface` service in the
application (InstanceSettingsBundle 1.5 registers one with `nowo_instance_settings.brand.enabled: true`), SeoKit reads
the brand name for an empty `defaults.site_name`, so the title suffix, `og:site_name` and a configured JSON-LD
Organization `name` follow the brand the admins edit. Nothing to configure: `nowo_seo_kit.brand` defaults to `auto`.

A host without InstanceSettings can provide the brand itself:

```php
use Nowo\Brand\Contract\Brand;
use Nowo\Brand\Contract\BrandColors;
use Nowo\Brand\Contract\BrandProviderInterface;

final class AppBrandProvider implements BrandProviderInterface
{
    public function getBrand(): Brand
    {
        $colors = new BrandColors('#1f6f54', '#134736', '#b42318', '#b54708', '#f3f6f4', '#0f1c18', '#ffffff');

        return new Brand('Acme', $colors, $colors);
    }
}
```

Opt in to the logo as fallback share image and Organization `logo` with `brand: { images: true }` (raster assets
only). Keys, precedence and URL rules: [CONFIGURATION.md — brand](CONFIGURATION.md#brand).

