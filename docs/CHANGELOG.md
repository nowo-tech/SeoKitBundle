# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/nowo-tech/SeoKitBundle/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/nowo-tech/SeoKitBundle/releases/tag/v1.0.0
