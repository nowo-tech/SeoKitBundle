<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\DependencyInjection;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use InvalidArgumentException;
use Nowo\SeoKitBundle\Command\SeoAuditCommand;
use Nowo\SeoKitBundle\Controller\Admin\SeoSiteSettingsController;
use Nowo\SeoKitBundle\Controller\Admin\SeoSurfaceController;
use Nowo\SeoKitBundle\Controller\Api\SeoSurfaceApiController;
use Nowo\SeoKitBundle\Doctrine\TablePrefixListener;
use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Entity\SeoSiteSettingsTranslation;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Form\SeoSiteSettingsType;
use Nowo\SeoKitBundle\Form\SeoSurfaceLocaleFilterType;
use Nowo\SeoKitBundle\Form\SeoSurfaceType;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Nowo\SeoKitBundle\Routing\SeoAdminRouteLoader;
use Nowo\SeoKitBundle\Security\AllowAllSeoKitAccessChecker;
use Nowo\SeoKitBundle\Security\ConfigurableSeoKitAccessChecker;
use Nowo\SeoKitBundle\Security\SeoKitAccessCheckerInterface;
use Nowo\SeoKitBundle\Service\AbsoluteUrlBuilder;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditSubjectProviderInterface;
use Nowo\SeoKitBundle\Service\ConfigPageHeadDefaultsProvider;
use Nowo\SeoKitBundle\Service\GeoRobotsGroupsProviderInterface;
use Nowo\SeoKitBundle\Service\HreflangSetBuilder;
use Nowo\SeoKitBundle\Service\OriginUrlGuard;
use Nowo\SeoKitBundle\Service\PageHeadContext;
use Nowo\SeoKitBundle\Service\PageHeadDefaultsProviderInterface;
use Nowo\SeoKitBundle\Service\PageHeadResolver;
use Nowo\SeoKitBundle\Service\PageHeadRuntimeBridge;
use Nowo\SeoKitBundle\Service\PageHeadSiteGraphProviderInterface;
use Nowo\SeoKitBundle\Service\PageHeadTitleComposerInterface;
use Nowo\SeoKitBundle\Service\Persistence\DoctrineSeoDefaultsProvider;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProvider;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProviderInterface;
use Nowo\SeoKitBundle\Service\SeoDefaultsProviderInterface;
use Nowo\SeoKitBundle\Service\SeoPencilCatalogFactory;
use Nowo\SeoKitBundle\Service\SeoSurfaceManager;
use Nowo\SeoKitBundle\Service\SiteIndexabilityProviderInterface;
use Nowo\SeoKitBundle\Ui\SeoKitAreaNav;
use Nowo\SeoKitBundle\Ui\SeoKitPages;
use Nowo\UiKitBundle\Navigation\AreaNavProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function array_key_exists;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function sprintf;
use function trim;

/**
 * Loads and exposes nowo_seo_kit configuration.
 */
final class SeoKitExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config             = $this->processConfiguration(new Configuration(), $configs);
        [$webUi, $security] = self::resolveWebUi($config);
        $config['web_ui']   = $webUi;
        $config['security'] = $security;

        $container->setParameter(Configuration::ALIAS . '.config', $config);
        $container->setParameter(Configuration::ALIAS . '.enabled', $config['enabled']);
        $container->setParameter(Configuration::ALIAS . '.x_robots_tag.enabled', $config['x_robots_tag']['enabled']);
        $container->setParameter(Configuration::ALIAS . '.templates', $config['templates']);
        $container->setParameter(Configuration::ALIAS . '.locales', $config['locales']);
        $container->setParameter(Configuration::ALIAS . '.persistence', $config['persistence']);
        $container->setParameter(Configuration::ALIAS . '.web_ui', $webUi);
        $container->setParameter(Configuration::ALIAS . '.security', $security);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $container->registerForAutoconfiguration(SeoDefaultsProviderInterface::class)
            ->addTag('nowo_seo_kit.defaults_provider');
        $container->registerForAutoconfiguration(SiteIndexabilityProviderInterface::class)
            ->addTag('nowo_seo_kit.indexability_provider');
        $container->registerForAutoconfiguration(GeoRobotsGroupsProviderInterface::class)
            ->addTag('nowo_seo_kit.geo_robots_groups_provider');
        $container->registerForAutoconfiguration(SeoAuditSubjectProviderInterface::class)
            ->addTag('nowo_seo_kit.audit_subject_provider');
        $container->registerForAutoconfiguration(PageHeadDefaultsProviderInterface::class)
            ->addTag('nowo_seo_kit.page_head_defaults_provider');
        $container->registerForAutoconfiguration(PageHeadSiteGraphProviderInterface::class)
            ->addTag('nowo_seo_kit.page_head_site_graph_provider');
        $container->registerForAutoconfiguration(PageHeadTitleComposerInterface::class)
            ->addTag('nowo_seo_kit.page_head_title_composer');

        $baseUrl = $config['base_url'] ?? null;
        $origin  = is_string($baseUrl) && $baseUrl !== '' ? rtrim($baseUrl, '/') : null;
        $guard   = new Definition(OriginUrlGuard::class);
        $guard->setAutowired(true);
        $guard->setAutoconfigured(true);
        $guard->setArgument('$origin', $origin);
        $container->setDefinition(OriginUrlGuard::class, $guard);

        if (is_string($baseUrl) && $baseUrl !== '') {
            $absolute = new Definition(AbsoluteUrlBuilder::class);
            $absolute->setAutowired(true);
            $absolute->setAutoconfigured(true);
            $absolute->setArgument('$baseUrl', $baseUrl);
            $container->setDefinition(AbsoluteUrlBuilder::class, $absolute);
            $this->registerPageHead($container, $config);
        }

        $adminLoader = new Definition(SeoAdminRouteLoader::class);
        $adminLoader->setAutowired(true);
        $adminLoader->setAutoconfigured(true);
        $adminLoader->setArgument('$adminEnabled', $webUi['enabled']);
        $adminLoader->setArgument('$settingsEnabled', $webUi['settings']);
        $adminLoader->setArgument('$surfacesEnabled', $webUi['surfaces']);
        $adminLoader->setArgument('$apiEnabled', $webUi['api_enabled']);
        $adminLoader->setArgument('$pathPrefix', $webUi['path_prefix']);
        $adminLoader->addTag('routing.loader');
        $container->setDefinition(SeoAdminRouteLoader::class, $adminLoader);

        if (($config['persistence']['enabled'] ?? false) === true) {
            $this->registerPersistence($container, $config['persistence']);
            $this->registerTablePrefix($container, (string) ($config['doctrine']['table_prefix'] ?? ''));
            $manager = new Definition(SeoSurfaceManager::class);
            $manager->setAutowired(true);
            $manager->setAutoconfigured(true);
            $container->setDefinition(SeoSurfaceManager::class, $manager);
        }

        if (($config['persistence']['register_audit_command'] ?? true) === true) {
            $this->registerAudit($container, $config);
        }

        if ($webUi['enabled']) {
            if (($config['persistence']['enabled'] ?? false) !== true) {
                throw new InvalidArgumentException('nowo_seo_kit.web_ui.enabled requires nowo_seo_kit.persistence.enabled.');
            }
            $this->registerAdmin($container, $config, $webUi, $security);
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependFormKitDefaults($container);

        if (!$container->hasExtension('doctrine')) {
            return;
        }

        $enabled = false;
        foreach ($container->getExtensionConfig($this->getAlias()) as $raw) {
            if (($raw['persistence']['enabled'] ?? false) === true) {
                $enabled = true;
                break;
            }
        }

        if (!$enabled) {
            return;
        }

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'NowoSeoKitBundle' => [
                        'type'      => 'attribute',
                        'dir'       => __DIR__ . '/../Entity',
                        'prefix'    => 'Nowo\\SeoKitBundle\\Entity',
                        'alias'     => 'NowoSeoKit',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);
    }

    private function prependFormKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_form_kit')) {
            return;
        }

        $hostHasProfile = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            $profiles = $cfg['profiles'] ?? null;
            if (is_array($profiles) && array_key_exists('seo_kit', $profiles)) {
                $hostHasProfile = true;
                break;
            }
        }

        if ($hostHasProfile) {
            return;
        }

        $container->prependExtensionConfig('nowo_form_kit', [
            'profiles' => [
                'seo_kit' => [
                    'alias'              => 'seo_kit',
                    'translation_domain' => 'NowoSeoKitBundle',
                    // Auto help / placeholder keys only when the catalogue has them (no raw keys in the pages).
                    'convention_requires_translation' => true,
                    // REQ-UI-003: no framework classes in the bundle forms; the UiKit form theme of
                    // web_ui.form_themes applies the theme slots (input, check, form_row, ...).
                    'defaults' => [
                        'attr'     => [],
                        'row_attr' => [],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $persistence
     */
    private function registerPersistence(ContainerBuilder $container, array $persistence): void
    {
        if (!class_exists(AttributeDriver::class)) {
            return; // @codeCoverageIgnore
        }

        $repo = new Definition(SeoSiteSettingsRepository::class);
        $repo->setAutowired(true);
        $repo->setAutoconfigured(true);
        $repo->setArgument('$environmentRobots', $persistence['fallback_robots'] ?? 'index, follow');
        $repo->setArgument('$environmentContactEmail', $persistence['fallback_contact_email'] ?? '');
        $repo->setArgument('$environmentSiteName', $persistence['fallback_site_name'] ?? '');
        $container->setDefinition(SeoSiteSettingsRepository::class, $repo);

        $surfaceRepo = new Definition(SeoSurfaceRepository::class);
        $surfaceRepo->setAutowired(true);
        $surfaceRepo->setAutoconfigured(true);
        $container->setDefinition(SeoSurfaceRepository::class, $surfaceRepo);

        $provider = new Definition(SeoSiteConfigProvider::class);
        $provider->setAutowired(true);
        $provider->setAutoconfigured(true);
        $provider->setArgument('$cache', new Reference('cache.app', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        $provider->setArgument('$fallbackRobots', $persistence['fallback_robots'] ?? 'index, follow');
        $provider->setArgument('$fallbackContactEmail', $persistence['fallback_contact_email'] ?? '');
        $provider->setArgument('$fallbackSiteName', $persistence['fallback_site_name'] ?? '');
        $container->setDefinition(SeoSiteConfigProvider::class, $provider);
        $container->setAlias(SeoSiteConfigProviderInterface::class, SeoSiteConfigProvider::class);

        $defaults = new Definition(DoctrineSeoDefaultsProvider::class);
        $defaults->setAutowired(true);
        $defaults->setAutoconfigured(true);
        $defaults->addTag('nowo_seo_kit.defaults_provider');
        $defaults->addTag('nowo_seo_kit.indexability_provider');
        $container->setDefinition(DoctrineSeoDefaultsProvider::class, $defaults);

        $container->setParameter(Configuration::ALIAS . '.persistence.entities', [
            SeoSiteSettings::class,
            SeoSiteSettingsTranslation::class,
            SeoSurface::class,
        ]);
    }

    /**
     * REQ-ORM-002: the listener only exists when a prefix is configured.
     */
    private function registerTablePrefix(ContainerBuilder $container, string $prefix): void
    {
        $container->setParameter(Configuration::ALIAS . '.doctrine.table_prefix', $prefix);
        if ($prefix === '') {
            return;
        }

        $listener = new Definition(TablePrefixListener::class);
        $listener->setArgument('$tablePrefix', $prefix);
        $listener->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata']);
        $container->setDefinition(TablePrefixListener::class, $listener);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function registerPageHead(ContainerBuilder $container, array $config): void
    {
        $pageHead = $config['page_head'] ?? [];
        $regions  = $pageHead['open_graph_regions'] ?? [];
        $container->setParameter(Configuration::ALIAS . '.page_head.open_graph_regions', $regions);

        $hreflang = new Definition(HreflangSetBuilder::class);
        $hreflang->setAutowired(true);
        $hreflang->setAutoconfigured(true);
        $hreflang->setArgument('$urls', new Reference(AbsoluteUrlBuilder::class));
        $hreflang->setArgument('$siteLocales', $config['locales'] ?? ['en']);
        $hreflang->setArgument('$defaultLocale', $config['default_locale'] ?? 'en');
        $container->setDefinition(HreflangSetBuilder::class, $hreflang);

        $yamlDefaults = new Definition(ConfigPageHeadDefaultsProvider::class);
        $yamlDefaults->setAutowired(true);
        $yamlDefaults->setAutoconfigured(true);
        $yamlDefaults->setArgument('$config', $config);
        $container->setDefinition(ConfigPageHeadDefaultsProvider::class, $yamlDefaults);
        $container->setAlias(PageHeadDefaultsProviderInterface::class, ConfigPageHeadDefaultsProvider::class);

        $resolver = new Definition(PageHeadResolver::class);
        $resolver->setAutowired(true);
        $resolver->setAutoconfigured(true);
        $resolver->setArgument('$urls', new Reference(AbsoluteUrlBuilder::class));
        $resolver->setArgument('$hreflangSetBuilder', new Reference(HreflangSetBuilder::class));
        $resolver->setArgument('$defaultsProvider', new Reference(PageHeadDefaultsProviderInterface::class));
        $resolver->setArgument('$siteGraphProvider', new Reference(PageHeadSiteGraphProviderInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE));
        $resolver->setArgument('$titleComposer', new Reference(PageHeadTitleComposerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE));
        $resolver->setArgument('$openGraphRegions', $regions);
        $container->setDefinition(PageHeadResolver::class, $resolver);

        $context = new Definition(PageHeadContext::class);
        $context->setAutowired(true);
        $context->setAutoconfigured(true);
        $context->addTag('kernel.reset', ['method' => 'reset']);
        $container->setDefinition(PageHeadContext::class, $context);

        $bridge = new Definition(PageHeadRuntimeBridge::class);
        $bridge->setAutowired(true);
        $bridge->setAutoconfigured(true);
        $container->setDefinition(PageHeadRuntimeBridge::class, $bridge);

        $pencil = new Definition(SeoPencilCatalogFactory::class);
        $pencil->setAutowired(true);
        $pencil->setAutoconfigured(true);
        $container->setDefinition(SeoPencilCatalogFactory::class, $pencil);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function registerAudit(ContainerBuilder $container, array $config): void
    {
        // SeoAuditRules + SeoAuditor are defined in Resources/config/services.yaml with
        // !tagged_iterator for subject providers. Do not setDefinition() them here — that
        // wiped the tagged iterator and left nowo:seo:audit with zero hosts (1.8.0 bug).

        $command = new Definition(SeoAuditCommand::class);
        $command->setAutowired(true);
        $command->setAutoconfigured(true);
        $command->setArgument('$locales', $config['locales'] ?? ['en']);
        $container->setDefinition(SeoAuditCommand::class, $command);
    }

    /**
     * Resolves `web_ui` / `security`, mapping the deprecated 1.x `admin` keys (a legacy key that is set wins), and
     * enforces the fail-closed access contract (REQ-UI-002): an empty role list needs a custom checker or
     * `allow_unauthenticated`.
     *
     * @param array<string, mixed> $config processed configuration
     *
     * @return array{0: array{enabled: bool, settings: bool, surfaces: bool, path_prefix: string|null, layout_template: string|null, templates: array<string, string>, form_themes: list<string>, api_enabled: bool, api_csrf_protection: bool}, 1: array{access_roles: list<string>, access_checker: string|null, allow_unauthenticated: bool}}
     */
    public static function resolveWebUi(array $config): array
    {
        /** @var array{enabled: bool, settings: bool, surfaces: bool, path_prefix: string|null, layout_template: string|null, templates: array<string, string>, form_themes: list<string>, api_enabled: bool, api_csrf_protection: bool} $webUi */
        $webUi = $config['web_ui'];
        /** @var array{access_roles: list<string>, access_checker: string|null, allow_unauthenticated: bool} $security */
        $security = $config['security'];
        /** @var array<string, bool|string|null> $legacy */
        $legacy = $config['admin'] ?? [];

        foreach (['enabled', 'settings', 'surfaces', 'api_enabled', 'api_csrf_protection'] as $key) {
            if (is_bool($legacy[$key] ?? null)) {
                $webUi[$key] = $legacy[$key];
            }
        }
        foreach (['settings_template' => 'settings', 'surfaces_index_template' => 'surfaces_index', 'surfaces_form_template' => 'surfaces_form'] as $key => $page) {
            if (is_string($legacy[$key] ?? null) && $legacy[$key] !== '') {
                $webUi['templates'][$page] = $legacy[$key];
            }
        }
        if (is_string($legacy['role'] ?? null)) {
            $role = trim($legacy['role']);
            if ($role === '' || in_array($role, Configuration::PUBLIC_ATTRIBUTES, true)) {
                throw new InvalidArgumentException(sprintf('nowo_seo_kit.admin.role "%s" would open the admin UI to everybody; set nowo_seo_kit.security.access_roles (or allow_unauthenticated for a demo).', $legacy['role']));
            }
            $security['access_roles'] = [$role];
        }

        $checker = $security['access_checker'];
        if ($webUi['enabled'] && $security['access_roles'] === [] && ($checker === null || $checker === '') && !$security['allow_unauthenticated']) {
            throw new InvalidArgumentException('nowo_seo_kit.security.access_roles is empty: set at least one role, a custom security.access_checker, or security.allow_unauthenticated (demo only).');
        }

        return [$webUi, $security];
    }

    /**
     * @param array<string, mixed> $config
     * @param array{enabled: bool, settings: bool, surfaces: bool, path_prefix: string|null, layout_template: string|null, templates: array<string, string>, form_themes: list<string>, api_enabled: bool, api_csrf_protection: bool} $webUi
     * @param array{access_roles: list<string>, access_checker: string|null, allow_unauthenticated: bool} $security
     */
    private function registerAdmin(ContainerBuilder $container, array $config, array $webUi, array $security): void
    {
        $locales       = $config['locales'] ?? ['en'];
        $defaultLocale = $config['default_locale'] ?? 'en';

        $this->registerAccessChecker($container, $security);

        $areaNav = new Definition(SeoKitAreaNav::class);
        $areaNav->setArgument('$urlGenerator', new Reference('router'));
        $areaNav->setArgument('$settings', $webUi['settings']);
        $areaNav->setArgument('$surfaces', $webUi['surfaces']);
        $areaNav->addTag(AreaNavProviderInterface::TAG);
        $container->setDefinition(SeoKitAreaNav::class, $areaNav);

        $pages = new Definition(SeoKitPages::class);
        $pages->setArgument('$layoutTemplate', $webUi['layout_template']);
        $pages->setArgument('$templates', $webUi['templates']);
        $pages->setArgument('$formThemes', $webUi['form_themes']);
        $pages->setArgument('$areaNav', new Reference(SeoKitAreaNav::class));
        $container->setDefinition(SeoKitPages::class, $pages);

        $settingsForm = new Definition(SeoSiteSettingsType::class);
        $settingsForm->setAutowired(true);
        $settingsForm->setAutoconfigured(true);
        $settingsForm->addTag('form.type');
        $container->setDefinition(SeoSiteSettingsType::class, $settingsForm);

        $surfaceForm = new Definition(SeoSurfaceType::class);
        $surfaceForm->setAutowired(true);
        $surfaceForm->setAutoconfigured(true);
        $surfaceForm->setArgument('$originUrlGuard', new Reference(OriginUrlGuard::class));
        $surfaceForm->addTag('form.type');
        $container->setDefinition(SeoSurfaceType::class, $surfaceForm);

        $filterForm = new Definition(SeoSurfaceLocaleFilterType::class);
        $filterForm->addTag('form.type');
        $container->setDefinition(SeoSurfaceLocaleFilterType::class, $filterForm);

        if ($webUi['settings']) {
            $settings = new Definition(SeoSiteSettingsController::class);
            $settings->setAutowired(true);
            $settings->setAutoconfigured(true);
            $settings->setArgument('$pages', new Reference(SeoKitPages::class));
            $settings->setArgument('$locales', $locales);
            $settings->setArgument('$defaultLocale', $defaultLocale);
            $settings->addTag('controller.service_arguments');
            $container->setDefinition(SeoSiteSettingsController::class, $settings);
        }

        if ($webUi['surfaces']) {
            $surfaces = new Definition(SeoSurfaceController::class);
            $surfaces->setAutowired(true);
            $surfaces->setAutoconfigured(true);
            $surfaces->setArgument('$pages', new Reference(SeoKitPages::class));
            $surfaces->setArgument('$locales', $locales);
            $surfaces->setArgument('$defaultLocale', $defaultLocale);
            $surfaces->addTag('controller.service_arguments');
            $container->setDefinition(SeoSurfaceController::class, $surfaces);
        }

        if ($webUi['api_enabled']) {
            $api = new Definition(SeoSurfaceApiController::class);
            $api->setAutowired(true);
            $api->setAutoconfigured(true);
            $api->setArgument('$csrfTokenManager', new Reference('security.csrf.token_manager', ContainerInterface::NULL_ON_INVALID_REFERENCE));
            $api->setArgument('$csrfProtection', $webUi['api_csrf_protection']);
            $api->setArgument('$locales', $locales);
            $api->addTag('controller.service_arguments');
            $container->setDefinition(SeoSurfaceApiController::class, $api);
        }
    }

    /**
     * REQ-UI-002: `allow_unauthenticated` → allow-all (demo only); `access_checker` → alias to the host service;
     * otherwise the role checker over `access_roles`.
     *
     * @param array{access_roles: list<string>, access_checker: string|null, allow_unauthenticated: bool} $security
     */
    private function registerAccessChecker(ContainerBuilder $container, array $security): void
    {
        if ($security['allow_unauthenticated']) {
            $container->setDefinition(AllowAllSeoKitAccessChecker::class, new Definition(AllowAllSeoKitAccessChecker::class));
            $container->setAlias(SeoKitAccessCheckerInterface::class, AllowAllSeoKitAccessChecker::class);

            return;
        }

        $checker = $security['access_checker'];
        if (is_string($checker) && $checker !== '') {
            $container->setAlias(SeoKitAccessCheckerInterface::class, $checker);

            return;
        }

        $default = new Definition(ConfigurableSeoKitAccessChecker::class);
        $default->setArgument('$authorizationChecker', new Reference('security.authorization_checker'));
        $default->setArgument('$accessRoles', $security['access_roles']);
        $container->setDefinition(ConfigurableSeoKitAccessChecker::class, $default);
        $container->setAlias(SeoKitAccessCheckerInterface::class, ConfigurableSeoKitAccessChecker::class);
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
