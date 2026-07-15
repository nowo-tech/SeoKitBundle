# Installation

## Requirements

- PHP >= 8.2, < 8.6
- Symfony >= 7.0 || >= 8.0
- Twig Bundle

## Composer

```bash
composer require nowo-tech/seo-kit-bundle
```

## Register the bundle

Symfony Flex registers the bundle automatically. Manual registration:

```php
// config/bundles.php
Nowo\SeoKitBundle\SeoKitBundle::class => ['all' => true],
```

## Routes

Import bundle routes (Flex recipe creates `config/routes/nowo_seo_kit.yaml`):

```yaml
nowo_seo_kit:
    resource: .
    type: nowo_seo_kit
```

This exposes `/sitemap.xml` and `/robots.txt` and can register static pages from configuration.

## Twig

Add to your base layout `<head>`:

```twig
{{ nowo_seo_head() }}
```

## Docker development (bundle contributors)

```bash
make up
make install
make test
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for hooks and QA targets.
