<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\DependencyInjection;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Nowo\SeoKitBundle\Command\SeoAuditCommand;
use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Entity\SeoSiteSettingsTranslation;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditor;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditRules;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditSubjectProviderInterface;
use Nowo\SeoKitBundle\Service\Persistence\DoctrineSeoDefaultsProvider;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProvider;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProviderInterface;
use Nowo\SeoKitBundle\Service\SeoDefaultsProviderInterface;
use Nowo\SeoKitBundle\Service\SiteIndexabilityProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

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

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $container->registerForAutoconfiguration(SeoDefaultsProviderInterface::class)
            ->addTag('nowo_seo_kit.defaults_provider');
        $container->registerForAutoconfiguration(SiteIndexabilityProviderInterface::class)
            ->addTag('nowo_seo_kit.indexability_provider');
        $container->registerForAutoconfiguration(SeoAuditSubjectProviderInterface::class)
            ->addTag('nowo_seo_kit.audit_subject_provider');

        if (($config['persistence']['enabled'] ?? false) === true) {
            $this->registerPersistence($container, $config['persistence']);
        }

        if (($config['persistence']['register_audit_command'] ?? true) === true) {
            $this->registerAudit($container, $config);
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('doctrine')) {
            return;
        }

        // Do not processConfiguration here: placeholders like %app.locales% are still unresolved.
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
        $rules = new Definition(SeoAuditRules::class);
        $rules->setAutowired(true);
        $rules->setAutoconfigured(true);
        $container->setDefinition(SeoAuditRules::class, $rules);

        $auditor = new Definition(SeoAuditor::class);
        $auditor->setAutowired(true);
        $auditor->setAutoconfigured(true);
        $container->setDefinition(SeoAuditor::class, $auditor);

        $command = new Definition(SeoAuditCommand::class);
        $command->setAutowired(true);
        $command->setAutoconfigured(true);
        $command->setArgument('$locales', $config['locales'] ?? ['en']);
        $container->setDefinition(SeoAuditCommand::class, $command);
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
