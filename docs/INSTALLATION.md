# Installation

## Table of contents

- [Requirements](#requirements)
- [Composer](#composer)
- [Register the bundle](#register-the-bundle)
- [Routes](#routes)
- [Twig](#twig)
- [Docker development (bundle contributors)](#docker-development-bundle-contributors)

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

## Twig Extra Bundle (REQ-TWIG-004)

This package ships Twig templates. Host applications **must** install and enable Twig Extra:

```bash
composer require twig/extra-bundle twig/string-extra
```

Register `Twig\Extra\TwigExtraBundle\TwigExtraBundle` in `config/bundles.php` (Flex usually does this). Demos already include the same stack. The package `release-check` runs `make check-twig-extra` to guard this contract.
