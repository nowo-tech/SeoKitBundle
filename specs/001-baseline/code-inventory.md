# Code inventory — baseline traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/seo-kit-bundle`  
**Last audited**: 2026-07-16

Every production artifact under `src/` is listed below.

## PHP classes (`src/**/*.php`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `SeoKitBundle.php` | Bundle entry | FR-SEO-007 |
| `Attribute/Seo.php` | Controller attribute overrides | FR-SEO-002 |
| `Controller/SeoKitController.php` | Sitemap / robots endpoints | FR-SEO-005 |
| `DependencyInjection/Configuration.php` | Config tree | FR-SEO-001 |
| `DependencyInjection/SeoKitExtension.php` | DI extension | FR-SEO-001 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Twig namespace paths | FR-SEO-006 |
| `EventSubscriber/SeoRuntimeClearSubscriber.php` | Request-scoped runtime reset | FR-SEO-002 |
| `Model/SeoMetadata.php` | Resolved metadata DTO | FR-SEO-002 |
| `Routing/SeoStaticRouteLoader.php` | Static page route loader | FR-SEO-007 |
| `Service/SeoMetadataResolver.php` | Layer merge + resolution | FR-SEO-002 |
| `Service/SeoPathBuilder.php` | URL / path building | FR-SEO-004 |
| `Service/SeoRuntime.php` | Runtime overrides | FR-SEO-002 |
| `Service/SeoTemplateRenderer.php` | Template placeholders | FR-SEO-003 |
| `Service/SitemapGenerator.php` | Sitemap XML | FR-SEO-005 |
| `Service/RobotsTxtGenerator.php` | robots.txt | FR-SEO-005 |
| `Twig/SeoKitExtension.php` | Twig helpers | FR-SEO-006 |

## Symfony config (`src/Resources/config/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Service wiring | FR-SEO-001 |
| `Resources/config/routes.yaml` | Bundle routes | FR-SEO-005 |

## Twig views (`src/Resources/views/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/seo/head.html.twig` | Default head partial | FR-SEO-006 |

## Translations (`src/Resources/translations/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/translations/NowoSeoKitBundle.en.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoSeoKitBundle.es.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoSeoKitBundle.fr.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoSeoKitBundle.de.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoSeoKitBundle.it.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoSeoKitBundle.pt.yaml` | i18n | FR-I18N-001 |
| `Resources/translations/NowoSeoKitBundle.nl.yaml` | i18n | FR-I18N-001 |
