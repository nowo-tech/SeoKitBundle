<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\DependencyInjection;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use InvalidArgumentException;
use Nowo\SeoKitBundle\Command\SeoAuditCommand;
use Nowo\SeoKitBundle\Controller\Admin\SeoSiteSettingsController;
use Nowo\SeoKitBundle\Controller\Admin\SeoSurfaceController;
use Nowo\SeoKitBundle\Controller\Api\SeoSurfaceApiController;
use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Entity\SeoSiteSettingsTranslation;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Form\SeoSiteSettingsType;
use Nowo\SeoKitBundle\Form\SeoSurfaceType;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Nowo\SeoKitBundle\Routing\SeoAdminRouteLoader;
use Nowo\SeoKitBundle\Service\AbsoluteUrlBuilder;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditSubjectProviderInterface;
use Nowo\SeoKitBundle\Service\OriginUrlGuard;
use Nowo\SeoKitBundle\Service\Persistence\DoctrineSeoDefaultsProvider;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProvider;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProviderInterface;
use Nowo\SeoKitBundle\Service\SeoDefaultsProviderInterface;
use Nowo\SeoKitBundle\Service\SeoSurfaceManager;
use Nowo\SeoKitBundle\Service\SiteIndexabilityProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function is_string;

/**
 * Loads and exposes nowo_seo_kit configuration.
 */
final class SeoKitExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter(Configuration::ALIAS . '.config', $config);
        $container->setParameter(Configuration::ALIAS . '.enabled', $config['enabled']);
        $container->setParameter(Configuration::ALIAS . '.templates', $config['templates']);
        $container->setParameter(Configuration::ALIAS . '.locales', $config['locales']);
        $container->setParameter(Configuration::ALIAS . '.persistence', $config['persistence']);
        $container->setParameter(Configuration::ALIAS . '.admin', $config['admin']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $container->registerForAutoconfiguration(SeoDefaultsProviderInterface::class)
            ->addTag('nowo_seo_kit.defaults_provider');
        $container->registerForAutoconfiguration(SiteIndexabilityProviderInterface::class)
            ->addTag('nowo_seo_kit.indexability_provider');
        $container->registerForAutoconfiguration(SeoAuditSubjectProviderInterface::class)
            ->addTag('nowo_seo_kit.audit_subject_provider');

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
        }

        $adminLoader = new Definition(SeoAdminRouteLoader::class);
        $adminLoader->setAutowired(true);
        $adminLoader->setAutoconfigured(true);
        $adminLoader->setArgument('$adminEnabled', ($config['admin']['enabled'] ?? false) === true);
        $adminLoader->setArgument('$settingsEnabled', ($config['admin']['settings'] ?? true) === true);
        $adminLoader->setArgument('$surfacesEnabled', ($config['admin']['surfaces'] ?? true) === true);
        $adminLoader->setArgument('$apiEnabled', ($config['admin']['api_enabled'] ?? true) === true);
        $adminLoader->addTag('routing.loader');
        $container->setDefinition(SeoAdminRouteLoader::class, $adminLoader);

        if (($config['persistence']['enabled'] ?? false) === true) {
            $this->registerPersistence($container, $config['persistence']);
            $manager = new Definition(SeoSurfaceManager::class);
            $manager->setAutowired(true);
            $manager->setAutoconfigured(true);
            $container->setDefinition(SeoSurfaceManager::class, $manager);
        }

        if (($config['persistence']['register_audit_command'] ?? true) === true) {
            $this->registerAudit($container, $config);
        }

        if (($config['admin']['enabled'] ?? false) === true) {
            if (($config['persistence']['enabled'] ?? false) !== true) {
                throw new InvalidArgumentException('nowo_seo_kit.admin.enabled requires nowo_seo_kit.persistence.enabled.');
            }
            $this->registerAdmin($container, $config);
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
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
     * @param array<string, mixed> $config
     */
    private function registerAdmin(ContainerBuilder $container, array $config): void
    {
        $admin         = $config['admin'] ?? [];
        $locales       = $config['locales'] ?? ['en'];
        $defaultLocale = $config['default_locale'] ?? 'en';
        $role          = $admin['role'] ?? 'ROLE_ADMIN';

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

        if (($admin['settings'] ?? true) === true) {
            $settings = new Definition(SeoSiteSettingsController::class);
            $settings->setAutowired(true);
            $settings->setAutoconfigured(true);
            $settings->setArgument('$locales', $locales);
            $settings->setArgument('$defaultLocale', $defaultLocale);
            $settings->setArgument('$role', $role);
            $settings->setArgument('$template', $admin['settings_template'] ?? '@NowoSeoKitBundle/admin/settings.html.twig');
            $settings->addTag('controller.service_arguments');
            $container->setDefinition(SeoSiteSettingsController::class, $settings);
        }

        if (($admin['surfaces'] ?? true) === true) {
            $surfaces = new Definition(SeoSurfaceController::class);
            $surfaces->setAutowired(true);
            $surfaces->setAutoconfigured(true);
            $surfaces->setArgument('$locales', $locales);
            $surfaces->setArgument('$defaultLocale', $defaultLocale);
            $surfaces->setArgument('$role', $role);
            $surfaces->setArgument('$indexTemplate', $admin['surfaces_index_template'] ?? '@NowoSeoKitBundle/admin/surfaces_index.html.twig');
            $surfaces->setArgument('$formTemplate', $admin['surfaces_form_template'] ?? '@NowoSeoKitBundle/admin/surfaces_form.html.twig');
            $surfaces->addTag('controller.service_arguments');
            $container->setDefinition(SeoSurfaceController::class, $surfaces);
        }

        if (($admin['api_enabled'] ?? true) === true) {
            $api = new Definition(SeoSurfaceApiController::class);
            $api->setAutowired(true);
            $api->setAutoconfigured(true);
            $api->setArgument('$role', $role);
            $api->addTag('controller.service_arguments');
            $container->setDefinition(SeoSurfaceApiController::class, $api);
        }
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
