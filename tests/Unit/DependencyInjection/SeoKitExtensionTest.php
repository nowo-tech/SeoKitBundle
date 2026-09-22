<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\DependencyInjection;

use InvalidArgumentException;
use Nowo\SeoKitBundle\Command\SeoAuditCommand;
use Nowo\SeoKitBundle\Controller\Admin\SeoSiteSettingsController;
use Nowo\SeoKitBundle\Controller\Admin\SeoSurfaceController;
use Nowo\SeoKitBundle\Controller\Api\SeoSurfaceApiController;
use Nowo\SeoKitBundle\DependencyInjection\Configuration;
use Nowo\SeoKitBundle\DependencyInjection\SeoKitExtension;
use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Entity\SeoSiteSettingsTranslation;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Form\SeoSiteSettingsType;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Nowo\SeoKitBundle\Routing\SeoAdminRouteLoader;
use Nowo\SeoKitBundle\Service\OriginUrlGuard;
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

    public function testLoadRegistersAdminWhenEnabledWithPersistence(): void
    {
        $container = new ContainerBuilder();
        $extension = new SeoKitExtension();

        $extension->load([[
            'enabled'     => true,
            'base_url'    => 'https://nowo.tech',
            'persistence' => ['enabled' => true],
            'admin'       => [
                'enabled'     => true,
                'settings'    => true,
                'surfaces'    => true,
                'api_enabled' => true,
            ],
        ]], $container);

        self::assertTrue($container->hasDefinition(SeoSiteSettingsController::class));
        self::assertTrue($container->hasDefinition(SeoSurfaceController::class));
        self::assertTrue($container->hasDefinition(SeoSurfaceApiController::class));
        self::assertTrue($container->hasDefinition(SeoSiteSettingsType::class));
        self::assertTrue($container->hasDefinition(SeoAdminRouteLoader::class));
        self::assertTrue($container->hasDefinition(OriginUrlGuard::class));
    }

    public function testLoadAdminCanDisableSubControllers(): void
    {
        $container = new ContainerBuilder();
        (new SeoKitExtension())->load([[
            'enabled'     => true,
            'persistence' => ['enabled' => true],
            'admin'       => [
                'enabled'     => true,
                'settings'    => false,
                'surfaces'    => false,
                'api_enabled' => false,
            ],
        ]], $container);

        self::assertFalse($container->hasDefinition(SeoSiteSettingsController::class));
        self::assertFalse($container->hasDefinition(SeoSurfaceController::class));
        self::assertFalse($container->hasDefinition(SeoSurfaceApiController::class));
        self::assertTrue($container->hasDefinition(SeoSiteSettingsType::class));
    }

    public function testLoadAdminWithoutPersistenceThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SeoKitExtension())->load([[
            'enabled'     => true,
            'admin'       => ['enabled' => true],
            'persistence' => ['enabled' => false],
        ]], new ContainerBuilder());
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
