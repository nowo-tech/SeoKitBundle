# Upgrading

## To 1.2.0

### Twig namespace rename

Logical Twig names moved from `@NowoSeoKit/...` to `@NowoSeoKitBundle/...`.

1. If you set `nowo_seo_kit.templates.head` explicitly, update it to `@NowoSeoKitBundle/seo/head.html.twig` (or your custom path).
2. Move application overrides from `templates/bundles/NowoSeoKit/` to `templates/bundles/NowoSeoKitBundle/`.
3. Clear cache: `php bin/console cache:clear`

```bash
composer require nowo-tech/seo-kit-bundle:^1.2
php bin/console cache:clear
```

### Breaking changes

- Default Twig namespace and default `templates.head` value changed (`NowoSeoKit` → `NowoSeoKitBundle`).
- Override directory path changed to match Symfony’s `BundleName` convention.

## To 1.1.0

### Hyphenated slug keys

`slugs.<route>.<slug>` keys with hyphens (e.g. `hello-world`) are **preserved**. Previously Symfony Config could normalize them to underscores (`hello_world`), which broke path building and sitemap entries.

If your deployed config already used underscore keys because of that normalization, either:

- rename keys to the real URL slug (`hello-world`), or
- keep underscore keys only if that is the slug you pass in the route.

No other breaking API changes. New helper: `SeoPathBuilder::resolveCanonicalSlug()`.

### Demo only

The FrankenPHP demo gained a locale switch, `/` as default-locale home, and `/admin/seo` CRUD. These are not required for integrators using the bundle in an application.

### Install

```bash
composer require nowo-tech/seo-kit-bundle:^1.1
```

## To 1.0.0

First public release. No prior versions.

### Install

```bash
composer require nowo-tech/seo-kit-bundle:^1.0
```

### Configuration

Ensure `config/packages/nowo_seo_kit.yaml` exists (Flex recipe or copy from `.symfony/recipe/`). Import routes via `config/routes/nowo_seo_kit.yaml`.

### Twig

Add `{{ nowo_seo_head() }}` to your base layout if not already present.

### Breaking changes

None (initial release).
