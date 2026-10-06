# Code inventory — baseline traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/seo-kit-bundle`  
**Last audited**: 2026-10-06 (v1.11.0)

Every production artifact under `src/` is listed below.

## PHP classes (`src/**/*.php`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `SeoKitBundle.php` | Bundle entry | FR-SEO-007 |
| `Attribute/Seo.php` | Controller attribute overrides | FR-SEO-002 |
| `Command/SeoAuditCommand.php` | `nowo:seo:audit` | FR-SEO-001 |
| `Controller/SeoKitController.php` | Sitemap / robots endpoints | FR-SEO-005 |
| `Controller/Admin/SeoSiteSettingsController.php` | Optional SEO admin settings | FR-SEO-001 |
| `Controller/Admin/SeoSurfaceController.php` | Optional surface CRUD | FR-SEO-001 |
| `Controller/Api/SeoSurfaceApiController.php` | Pencil JSON API | FR-SEO-001 |
| `DependencyInjection/Configuration.php` | Config tree (`robots.groups`, PageHead, persistence, admin) | FR-SEO-001, FR-SEO-008, FR-SEO-010 |
| `DependencyInjection/SeoKitExtension.php` | DI extension | FR-SEO-001 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Twig namespace paths | FR-SEO-006 |
| `Entity/SeoSiteSettings.php` | Optional Doctrine site settings | FR-SEO-001 |
| `Entity/SeoSiteSettingsTranslation.php` | Site settings i18n | FR-I18N-001 |
| `Entity/SeoSurface.php` | Surface override entity | FR-SEO-001 |
| `EventSubscriber/SeoRuntimeClearSubscriber.php` | Request-scoped runtime reset | FR-SEO-002 |
| `Form/AbstractSeoFormType.php` | FormKit admin forms | FR-SEO-001 |
| `Form/SeoSiteSettingsType.php` | Site settings form | FR-SEO-001 |
| `Form/SeoSurfaceType.php` | Surface form | FR-SEO-001 |
| `Model/SeoMetadata.php` | Resolved metadata DTO | FR-SEO-002 |
| `Model/SeoSiteConfig.php` | Persistence snapshot | FR-SEO-001 |
| `Model/HreflangSet.php` | Hreflang DTO | FR-SEO-004 |
| `Model/PageHead.php` | PageHead DTO | FR-SEO-010 |
| `Model/PageHeadDefaults.php` | PageHead defaults | FR-SEO-010 |
| `Model/PageHeadInput.php` | PageHead input | FR-SEO-010 |
| `Model/StructuredData/AbstractNode.php` | JSON-LD node base | FR-SEO-009 |
| `Model/StructuredData/StructuredDataNode.php` | JSON-LD node contract | FR-SEO-009 |
| `Model/StructuredData/StructuredDataGraph.php` | JSON-LD graph encoder | FR-SEO-009 |
| `Model/StructuredData/OrganizationNode.php` | Organization JSON-LD | FR-SEO-009 |
| `Model/StructuredData/WebSiteNode.php` | WebSite JSON-LD | FR-SEO-009 |
| `Model/StructuredData/WebPageNode.php` | WebPage JSON-LD | FR-SEO-009 |
| `Model/StructuredData/BreadcrumbListNode.php` | BreadcrumbList JSON-LD | FR-SEO-009 |
| `Model/StructuredData/SoftwareApplicationNode.php` | SoftwareApplication JSON-LD | FR-SEO-009 |
| `Model/StructuredData/LocalBusinessNode.php` | LocalBusiness JSON-LD | FR-SEO-009 |
| `Model/StructuredData/PersonNode.php` | Person JSON-LD | FR-SEO-009 |
| `Model/StructuredData/FaqPageNode.php` | FAQPage JSON-LD | FR-SEO-009 |
| `Model/StructuredData/BlogPostingNode.php` | BlogPosting JSON-LD | FR-SEO-009 |
| `Repository/ResetsClosedEntityManagerTrait.php` | Worker-safe EM reset | FR-SEO-001 |
| `Repository/SeoSiteSettingsRepository.php` | Site settings persistence | FR-SEO-001 |
| `Repository/SeoSurfaceRepository.php` | Surface persistence | FR-SEO-001 |
| `Routing/SeoStaticRouteLoader.php` | Static page route loader | FR-SEO-007 |
| `Routing/SeoAdminRouteLoader.php` | Admin route loader | FR-SEO-007 |
| `Service/SeoMetadataResolver.php` | Layer merge + resolution | FR-SEO-002 |
| `Service/SeoPathBuilder.php` | URL / path building | FR-SEO-004 |
| `Service/SeoPathBuilderInterface.php` | Path builder contract | FR-SEO-004 |
| `Service/AbsoluteUrlBuilder.php` | Origin + path normalisation | FR-SEO-004 |
| `Service/SeoRuntime.php` | Runtime overrides | FR-SEO-002 |
| `Service/SeoTemplateRenderer.php` | Template placeholders | FR-SEO-003 |
| `Service/SitemapGenerator.php` | Sitemap XML | FR-SEO-005 |
| `Service/RobotsTxtGenerator.php` | robots.txt (default + extra groups) | FR-SEO-005, FR-SEO-008 |
| `Service/GeoRobotsGroupsProviderInterface.php` | Host extra robots groups | FR-SEO-008 |
| `Service/SeoDefaultsProviderInterface.php` | Host defaults SPI | FR-SEO-001 |
| `Service/SiteIndexabilityProviderInterface.php` | Indexability SPI | FR-SEO-001 |
| `Service/SitemapUrlProviderInterface.php` | Extra sitemap URLs SPI | FR-SEO-005 |
| `Service/HreflangSetBuilder.php` | Hreflang set builder | FR-SEO-004 |
| `Service/PageHeadContext.php` | Request-scoped PageHead | FR-SEO-010 |
| `Service/PageHeadDefaultsProviderInterface.php` | PageHead defaults SPI | FR-SEO-010 |
| `Service/PageHeadResolver.php` | PageHead pipeline | FR-SEO-010 |
| `Service/PageHeadRuntimeBridge.php` | PageHead → Twig runtime | FR-SEO-010 |
| `Service/PageHeadSiteGraphProviderInterface.php` | PageHead site graph SPI | FR-SEO-010 |
| `Service/PageHeadTitleComposerInterface.php` | PageHead title SPI | FR-SEO-010 |
| `Service/SeoPencilCatalogFactory.php` | Public pencil JSON catalog | FR-SEO-001 |
| `Service/SeoSurfaceKeys.php` | Conventional surface keys | FR-SEO-001 |
| `Service/SeoSurfaceManager.php` | Surface persistence helper | FR-SEO-001 |
| `Service/OriginUrlGuard.php` | Admin/API origin guard | FR-SEO-001 |
| `Service/Persistence/DoctrineSeoDefaultsProvider.php` | DB-backed defaults | FR-SEO-001 |
| `Service/Persistence/SeoSiteConfigProvider.php` | Cached site config snapshot | FR-SEO-001 |
| `Service/Persistence/SeoSiteConfigProviderInterface.php` | Site config contract | FR-SEO-001 |
| `Service/StructuredData/SiteStructuredDataFactory.php` | Site JSON-LD factory | FR-SEO-009 |
| `Service/Audit/SeoAuditor.php` | Audit runner | FR-SEO-001 |
| `Service/Audit/SeoAuditRules.php` | Audit rules | FR-SEO-001 |
| `Service/Audit/SeoAuditRow.php` | Audit row DTO | FR-SEO-001 |
| `Service/Audit/SeoAuditSubjectProviderInterface.php` | Audit subjects SPI | FR-SEO-001 |
| `Twig/SeoKitExtension.php` | Twig helpers | FR-SEO-006 |

## Symfony config (`src/Resources/config/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Service wiring | FR-SEO-001 |
| `Resources/config/routes.yaml` | Bundle routes | FR-SEO-005 |
| `Resources/config/routes_admin.yaml` | Admin routes | FR-SEO-007 |

## Twig views (`src/Resources/views/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/seo/head.html.twig` | Default head partial | FR-SEO-006 |
| `Resources/views/admin/settings.html.twig` | Admin site settings | FR-SEO-001 |
| `Resources/views/admin/surfaces_index.html.twig` | Surface list | FR-SEO-001 |
| `Resources/views/admin/surfaces_form.html.twig` | Surface form | FR-SEO-001 |

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
