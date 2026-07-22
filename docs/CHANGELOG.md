# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] - 2026-07-22

### Changed

- **Twig namespace** — templates resolve under `@NowoSeoKitBundle/...` (was `@NowoSeoKit/...`)
- Default `templates.head` is now `@NowoSeoKitBundle/seo/head.html.twig`

### Added

- Application overrides via `templates/bundles/NowoSeoKitBundle/` are prepended so they win over bundle views (REQ-TWIG-002)
- `TwigPathsPass` resolves `twig.loader.native` alias and common filesystem loader service ids

### Documentation

- [CONFIGURATION.md](CONFIGURATION.md) and [UPGRADING.md](UPGRADING.md) updated for the Twig namespace rename

## [1.1.0] - 2026-07-18

### Added

- `SeoPathBuilder::resolveCanonicalSlug()` — maps a request slug (canonical or translated) to the configured slug key
- Demo: locale switch (EN / ES / FR) using SEO alternates (locale + localized path / translated slug)
- Demo: optional `_locale` on home (`/` → default locale) via `trailing_slash_on_root: false`
- Demo: SEO Admin CRUD at `/admin/seo` for `pages`, `slug_routes`, and `slugs` (writes `nowo_seo_kit.yaml`)
- Bundle-local `make update-deps` scripts (REQ-MAKE-008): composer update in the bundle **and** demos

### Fixed

- Preserve hyphenated keys under `slugs.*` (e.g. `hello-world`); Symfony Config no longer normalizes them to underscores
- Hreflang / sitemap / metadata for translated slug URLs (e.g. `/es/blog/hola-mundo`) resolve via the canonical slug config

### Documentation

- Demo FrankenPHP docs: root URL, locale switch, SEO Admin
- Upgrading notes for hyphenated slug keys

## [1.0.0] - 2026-07-16

### Added

- First stable release of **SeoKitBundle**
- Hierarchical SEO config: defaults → slug_routes → pages → slugs → `#[Seo]` attribute → `SeoRuntime`
- Twig helpers `nowo_seo_head()` and `nowo_seo_metadata()` (Open Graph, Twitter, JSON-LD, canonical, hreflang)
- Multilingual static routes and translated slug paths
- Route loader type `nowo_seo_kit` registers `/sitemap.xml`, `/robots.txt`, and optional static pages with `controller`
- FrankenPHP demo (Symfony 8)
- PHPUnit unit tests with high line coverage
- GitHub Actions CI, Flex recipe, and documentation pack

[Unreleased]: https://github.com/nowo-tech/SeoKitBundle/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.2.0
[1.1.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.1.0
[1.0.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.0.0
