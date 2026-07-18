# Seo Kit Bundle

[![CI](https://github.com/nowo-tech/SeoKitBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/SeoKitBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/seo-kit-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/seo-kit-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/seo-kit-bundle.svg)](https://packagist.org/packages/nowo-tech/seo-kit-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com) [![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** Give it a star on GitHub! It helps us maintain and improve the project.

Symfony SEO kit: multilingual static routes, slug SEO layers (defaults / pages / slug patterns / specific slugs), head tags, hreflang, sitemap and robots for FrankenPHP, php-fpm and Nginx.

## Features

- ✅ **Hierarchical SEO config** — defaults → slug_routes → pages → slugs → attribute → runtime
- ✅ **Twig head helper** — `{{ nowo_seo_head() }}` with Open Graph, Twitter, JSON-LD, canonical, hreflang
- ✅ **Multilingual paths** — locale-specific paths and translated slugs
- ✅ **Sitemap & robots** — `/sitemap.xml` and `/robots.txt` served by Symfony
- ✅ **Runtime overrides** — `SeoRuntime` for dynamic titles from controllers
- ✅ **PHP 8 attribute** — `#[Seo]` on controllers
- ✅ **FrankenPHP-ready demo** — single-container Symfony 8 demo

**FrankenPHP:** Demos use a **single PHP service** (FrankenPHP, no nginx). With **`APP_ENV=dev`** (default), the Docker **entrypoint swaps in `Caddyfile.dev`** — **`php_server` without workers** for comfortable local development. The baked-in production `Caddyfile` can use **`php_server { worker … }`**; see [docs/DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md). Access the demo at `http://localhost:PORT` (see `demo/README.md` and `.env.example`).

## Installation

```bash
composer require nowo-tech/seo-kit-bundle
```

Register the bundle in `config/bundles.php` (Flex does this automatically):

```php
Nowo\SeoKitBundle\SeoKitBundle::class => ['all' => true],
```

Add to your base layout:

```twig
{{ nowo_seo_head() }}
```

## Requirements

- PHP >= 8.2, < 8.6
- Symfony >= 7.0 || >= 8.0

## Documentation

- [GitHub Actions CI requirements](docs/GITHUB_CI.md)
- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [Demo with FrankenPHP](docs/DEMO-FRANKENPHP.md) (includes worker mode)
- [Server cookbook (Nginx, php-fpm, FrankenPHP)](docs/SERVERS.md)

## Version information

| Version | PHP | Symfony | Status |
|---------|-----|---------|--------|
| 1.1.x | >= 8.2 | 7.0 – 8.1+ | Stable |
| 1.0.x | >= 8.2 | 7.0 – 8.1+ | Stable |

## Demos

```bash
make -C demo up-symfony8   # http://localhost:8050 (default PORT)
```

Demo extras: locale switch, `/` default-locale home, SEO Admin at `/admin/seo`. See [docs/DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md).

## Tests and coverage

```bash
make test
make test-coverage
```

- Tests: PHPUnit (unit)
- - PHP: **100%** lines (`make test-coverage`)

## License

MIT — see [LICENSE](LICENSE).
