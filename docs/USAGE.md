# Usage

## Table of contents

- [Twig](#twig)
- [PHP attribute](#php-attribute)
- [Runtime overrides](#runtime-overrides)
- [Sitemap and robots](#sitemap-and-robots)
- [Overriding templates (REQ-TWIG-001)](#overriding-templates-req-twig-001)
- [Translations](#translations)
- [Demo](#demo)
- [Dependency updates (maintainers)](#dependency-updates-maintainers)

## Twig

```twig
{# base.html.twig #}
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{ nowo_seo_head() }}
</head>
```

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
    ]);
    $seo->setVariables(['title' => 'Article title']);

    return $this->render('blog/show.html.twig');
}
```

Runtime is cleared automatically at the end of each request.

## Sitemap and robots

After configuration, verify:

```bash
curl -s https://your-host/sitemap.xml | head
curl -s https://your-host/robots.txt
```

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

| Subpath | Purpose |
| --- | --- |
| `seo/head.html.twig` | HTML head tags rendered by `nowo_seo_head()` (title, meta, Open Graph, Twitter, JSON-LD, alternates) |

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
