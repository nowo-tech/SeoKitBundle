<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\DependencyInjection;

use Nowo\SeoKitBundle\Command\SeoAuditCommand;
use Nowo\SeoKitBundle\DependencyInjection\Configuration;
use Nowo\SeoKitBundle\DependencyInjection\SeoKitExtension;
use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Entity\SeoSiteSettingsTranslation;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Nowo\SeoKitBundle\Service\Persistence\DoctrineSeoDefaultsProvider;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProvider;
use Nowo\SeoKitBundle\Service\SeoMetadataResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

final class SeoKitExtensionTest extends TestCase
{
    public function testLoadProcessesConfigurationAndRegistersServices(): void
    {
        $container = new ContainerBuilder();
        $extension = new SeoKitExtension();

        $extension->load([['enabled' => true]], $container);

        self::assertSame('nowo_seo_kit', $extension->getAlias());
        self::assertTrue($container->hasParameter(Configuration::ALIAS . '.config'));
        self::assertTrue($container->hasParameter(Configuration::ALIAS . '.enabled'));
        self::assertTrue($container->hasParameter(Configuration::ALIAS . '.templates'));
        self::assertTrue($container->hasDefinition(SeoMetadataResolver::class));
        self::assertTrue($container->hasDefinition(SeoAuditCommand::class));
        self::assertFalse($container->hasDefinition(SeoSiteSettingsRepository::class));
    }

    public function testLoadRegistersPersistenceWhenEnabled(): void
    {
        $container = new ContainerBuilder();
        $extension = new SeoKitExtension();

        $extension->load([[
            'enabled'     => true,
            'persistence' => [
                'enabled'                => true,
                'fallback_robots'        => 'index, follow',
                'fallback_contact_email' => 'ops@nowo.tech',
                'fallback_site_name'     => 'Nowo',
                'register_audit_command' => false,
            ],
        ]], $container);

        self::assertTrue($container->hasDefinition(SeoSiteSettingsRepository::class));
        self::assertTrue($container->hasDefinition(SeoSurfaceRepository::class));
        self::assertTrue($container->hasDefinition(SeoSiteConfigProvider::class));
        self::assertTrue($container->hasDefinition(DoctrineSeoDefaultsProvider::class));
        self::assertFalse($container->hasDefinition(SeoAuditCommand::class));
        self::assertSame(
            [
                SeoSiteSettings::class,
                SeoSiteSettingsTranslation::class,
                SeoSurface::class,
            ],
            $container->getParameter(Configuration::ALIAS . '.persistence.entities'),
        );
    }

    public function testPrependRegistersDoctrineMappingWhenPersistenceEnabled(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'doctrine';
            }
        });
        $container->prependExtensionConfig('nowo_seo_kit', [
            'persistence' => ['enabled' => true],
        ]);

        (new SeoKitExtension())->prepend($container);

        $doctrine = $container->getExtensionConfig('doctrine');
        self::assertNotSame([], $doctrine);
        self::assertArrayHasKey('NowoSeoKitBundle', $doctrine[0]['orm']['mappings']);
    }

    public function testPrependIsNoopWithoutDoctrineOrPersistence(): void
    {
        $container = new ContainerBuilder();
        (new SeoKitExtension())->prepend($container);
        self::assertSame([], $container->getExtensionConfig('doctrine'));

        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'doctrine';
            }
        });
        (new SeoKitExtension())->prepend($container);
        self::assertSame([], $container->getExtensionConfig('doctrine'));
    }
}
