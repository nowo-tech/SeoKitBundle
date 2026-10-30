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
use Nowo\SeoKitBundle\Doctrine\TablePrefixListener;
use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Entity\SeoSiteSettingsTranslation;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Form\SeoSiteSettingsType;
use Nowo\SeoKitBundle\Form\SeoSurfaceLocaleFilterType;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Nowo\SeoKitBundle\Routing\SeoAdminRouteLoader;
use Nowo\SeoKitBundle\Security\AllowAllSeoKitAccessChecker;
use Nowo\SeoKitBundle\Security\ConfigurableSeoKitAccessChecker;
use Nowo\SeoKitBundle\Security\SeoKitAccessCheckerInterface;
use Nowo\SeoKitBundle\Service\AbsoluteUrlBuilder;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditor;
use Nowo\SeoKitBundle\Service\ConfigPageHeadDefaultsProvider;
use Nowo\SeoKitBundle\Service\OriginUrlGuard;
use Nowo\SeoKitBundle\Service\PageHeadDefaultsProviderInterface;
use Nowo\SeoKitBundle\Service\PageHeadResolver;
use Nowo\SeoKitBundle\Service\Persistence\DoctrineSeoDefaultsProvider;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProvider;
use Nowo\SeoKitBundle\Service\SeoMetadataResolver;
use Nowo\SeoKitBundle\Service\SeoSurfaceManager;
use Nowo\SeoKitBundle\Ui\SeoKitAreaNav;
use Nowo\SeoKitBundle\Ui\SeoKitPages;
use Nowo\UiKitBundle\Navigation\AreaNavProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

use function explode;

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
        self::assertTrue($container->hasDefinition(SeoSurfaceManager::class));
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
            'web_ui'      => [
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

        $api = $container->getDefinition(SeoSurfaceApiController::class);
        self::assertTrue($api->getArgument('$csrfProtection'));
        foreach ([SeoSiteSettingsController::class, SeoSurfaceController::class, SeoSurfaceApiController::class] as $controller) {
            $definition = $container->getDefinition($controller);
            self::assertFalse($definition->isAutoconfigured(), $controller);
            self::assertTrue($definition->hasTag('controller.service_arguments'), $controller);
            self::assertTrue($definition->hasTag('container.service_subscriber'), $controller);
            self::assertFalse($definition->hasTag('routing.controller'), $controller);
        }
        $manager = $api->getArgument('$csrfTokenManager');
        self::assertInstanceOf(Reference::class, $manager);
        self::assertSame('security.csrf.token_manager', (string) $manager);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $manager->getInvalidBehavior());
    }

    public function testLoadAdminApiCsrfCanBeDisabled(): void
    {
        $container = new ContainerBuilder();
        (new SeoKitExtension())->load([[
            'persistence' => ['enabled' => true],
            'web_ui'      => ['enabled' => true, 'api_csrf_protection' => false],
        ]], $container);

        self::assertFalse($container->getDefinition(SeoSurfaceApiController::class)->getArgument('$csrfProtection'));
    }

    public function testTablePrefixListenerIsRegisteredOnlyWithAPrefix(): void
    {
        $without = new ContainerBuilder();
        (new SeoKitExtension())->load([['persistence' => ['enabled' => true]]], $without);
        self::assertFalse($without->hasDefinition(TablePrefixListener::class));
        self::assertSame('', $without->getParameter(Configuration::ALIAS . '.doctrine.table_prefix'));

        $with = new ContainerBuilder();
        (new SeoKitExtension())->load([[
            'persistence' => ['enabled' => true],
            'doctrine'    => ['table_prefix' => 'app_'],
        ]], $with);
        $listener = $with->getDefinition(TablePrefixListener::class);
        self::assertSame('app_', $listener->getArgument('$tablePrefix'));
        self::assertSame([['event' => 'loadClassMetadata']], $listener->getTag('doctrine.event_listener'));
        self::assertSame('app_', $with->getParameter(Configuration::ALIAS . '.doctrine.table_prefix'));

        $noPersistence = new ContainerBuilder();
        (new SeoKitExtension())->load([['doctrine' => ['table_prefix' => 'app_']]], $noPersistence);
        self::assertFalse($noPersistence->hasDefinition(TablePrefixListener::class));
    }

    public function testTablePrefixRejectsUnsafeCharacters(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new SeoKitExtension())->load([['doctrine' => ['table_prefix' => 'app-; drop']]], new ContainerBuilder());
    }

    public function testLoadAdminCanDisableSubControllers(): void
    {
        $container = new ContainerBuilder();
        (new SeoKitExtension())->load([[
            'enabled'     => true,
            'persistence' => ['enabled' => true],
            'web_ui'      => [
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
            'web_ui'      => ['enabled' => true],
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

    public function testLoadKeepsTaggedIteratorOnSeoAuditorWhenAuditCommandRegisters(): void
    {
        $container = new ContainerBuilder();
        $extension = new SeoKitExtension();

        $extension->load([[
            'enabled'     => true,
            'base_url'    => 'https://example.test',
            'persistence' => [
                'enabled'                => true,
                'register_audit_command' => true,
            ],
        ]], $container);

        self::assertTrue($container->hasDefinition(SeoAuditor::class));
        $argument = $container->getDefinition(SeoAuditor::class)->getArgument('$subjectProviders');
        self::assertInstanceOf(TaggedIteratorArgument::class, $argument);
        self::assertSame('nowo_seo_kit.audit_subject_provider', $argument->getTag());

        self::assertTrue($container->hasDefinition(AbsoluteUrlBuilder::class));
        self::assertSame('https://example.test', $container->getDefinition(AbsoluteUrlBuilder::class)->getArgument('$baseUrl'));
        self::assertTrue($container->hasDefinition(ConfigPageHeadDefaultsProvider::class));
        self::assertTrue($container->hasAlias(PageHeadDefaultsProviderInterface::class));
        self::assertTrue($container->hasDefinition(PageHeadResolver::class));
    }

    public function testPrependRegistersSeoKitFormKitProfileWhenMissing(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'nowo_form_kit';
            }
        });

        (new SeoKitExtension())->prepend($container);

        $configs = $container->getExtensionConfig('nowo_form_kit');
        self::assertNotSame([], $configs);
        self::assertArrayHasKey('seo_kit', $configs[0]['profiles']);
        self::assertSame('NowoSeoKitBundle', $configs[0]['profiles']['seo_kit']['translation_domain']);
    }

    public function testPrependSkipsSeoKitProfileWhenHostAlreadyDefinesIt(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'nowo_form_kit';
            }
        });
        $container->prependExtensionConfig('nowo_form_kit', [
            'profiles' => [
                'seo_kit' => [
                    'alias'              => 'custom',
                    'translation_domain' => 'messages',
                ],
            ],
        ]);

        (new SeoKitExtension())->prepend($container);

        $configs = $container->getExtensionConfig('nowo_form_kit');
        self::assertCount(1, $configs);
        self::assertSame('custom', $configs[0]['profiles']['seo_kit']['alias']);
    }

    public function testPrependedFormKitProfileHasNoFrameworkClasses(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'nowo_form_kit';
            }
        });

        (new SeoKitExtension())->prepend($container);

        $profile = $container->getExtensionConfig('nowo_form_kit')[0]['profiles']['seo_kit'];
        self::assertSame(['attr' => [], 'row_attr' => []], $profile['defaults']);
        self::assertTrue($profile['convention_requires_translation']);
        self::assertArrayNotHasKey('field_types', $profile);
    }

    public function testWebUiDefaultsAndRoleCheckerWiring(): void
    {
        $container = new ContainerBuilder();
        (new SeoKitExtension())->load([[
            'persistence' => ['enabled' => true],
            'web_ui'      => ['enabled' => true, 'path_prefix' => '/admin/seo', 'layout_template' => 'admin/layout.html.twig'],
            'security'    => ['access_roles' => ['ROLE_SEO', 'ROLE_ADMIN']],
        ]], $container);

        $webUi = $container->getParameter(Configuration::ALIAS . '.web_ui');
        self::assertIsArray($webUi);
        self::assertTrue($webUi['enabled']);
        self::assertSame(SeoKitPages::TEMPLATES, $webUi['templates']);
        self::assertSame([SeoKitPages::DEFAULT_FORM_THEME], $webUi['form_themes']);
        self::assertSame(['access_roles' => ['ROLE_SEO', 'ROLE_ADMIN'], 'access_checker' => null, 'allow_unauthenticated' => false], $container->getParameter(Configuration::ALIAS . '.security'));

        self::assertSame(ConfigurableSeoKitAccessChecker::class, (string) $container->getAlias(SeoKitAccessCheckerInterface::class));
        $checker = $container->getDefinition(ConfigurableSeoKitAccessChecker::class);
        self::assertSame(['ROLE_SEO', 'ROLE_ADMIN'], $checker->getArgument('$accessRoles'));
        self::assertSame('security.authorization_checker', (string) $checker->getArgument('$authorizationChecker'));

        $pages = $container->getDefinition(SeoKitPages::class);
        self::assertSame('admin/layout.html.twig', $pages->getArgument('$layoutTemplate'));
        self::assertSame(SeoKitPages::TEMPLATES, $pages->getArgument('$templates'));
        self::assertSame([AreaNavProviderInterface::TAG => [[]]], $container->getDefinition(SeoKitAreaNav::class)->getTags());
        self::assertSame('/admin/seo', $container->getDefinition(SeoAdminRouteLoader::class)->getArgument('$pathPrefix'));
        self::assertTrue($container->hasDefinition(SeoSurfaceLocaleFilterType::class));
        self::assertSame(['en', 'es', 'fr', 'de', 'it', 'pt', 'nl'], $container->getDefinition(SeoSurfaceApiController::class)->getArgument('$locales'));
    }

    public function testCustomAccessCheckerAndAllowUnauthenticated(): void
    {
        $custom = new ContainerBuilder();
        (new SeoKitExtension())->load([[
            'persistence' => ['enabled' => true],
            'web_ui'      => ['enabled' => true],
            'security'    => ['access_roles' => [], 'access_checker' => 'app.seo_access'],
        ]], $custom);
        self::assertSame('app.seo_access', (string) $custom->getAlias(SeoKitAccessCheckerInterface::class));
        self::assertFalse($custom->hasDefinition(ConfigurableSeoKitAccessChecker::class));

        $demo = new ContainerBuilder();
        (new SeoKitExtension())->load([[
            'persistence' => ['enabled' => true],
            'web_ui'      => ['enabled' => true],
            'security'    => ['access_roles' => [], 'allow_unauthenticated' => true],
        ]], $demo);
        self::assertSame(AllowAllSeoKitAccessChecker::class, (string) $demo->getAlias(SeoKitAccessCheckerInterface::class));
    }

    public function testEmptyAccessRolesWithoutCheckerFailClosed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('security.access_roles is empty');
        (new SeoKitExtension())->load([[
            'persistence' => ['enabled' => true],
            'web_ui'      => ['enabled' => true],
            'security'    => ['access_roles' => []],
        ]], new ContainerBuilder());
    }

    public function testEmptyAccessRolesAreAcceptedWhileTheWebUiIsDisabled(): void
    {
        $container = new ContainerBuilder();
        (new SeoKitExtension())->load([['security' => ['access_roles' => []]]], $container);

        self::assertFalse($container->hasAlias(SeoKitAccessCheckerInterface::class));
    }

    public function testLegacyAdminKeysMapOntoWebUiAndSecurity(): void
    {
        [$webUi, $security] = SeoKitExtension::resolveWebUi((new Processor())->processConfiguration(new Configuration(), [[
            'admin' => [
                'enabled'                 => true,
                'settings'                => false,
                'surfaces'                => true,
                'role'                    => 'ROLE_SEO',
                'settings_template'       => 'a.html.twig',
                'surfaces_index_template' => 'b.html.twig',
                'surfaces_form_template'  => 'c.html.twig',
                'api_enabled'             => false,
                'api_csrf_protection'     => false,
            ],
        ]]));

        self::assertTrue($webUi['enabled']);
        self::assertFalse($webUi['settings']);
        self::assertTrue($webUi['surfaces']);
        self::assertFalse($webUi['api_enabled']);
        self::assertFalse($webUi['api_csrf_protection']);
        self::assertSame(['settings' => 'a.html.twig', 'surfaces_index' => 'b.html.twig', 'surfaces_form' => 'c.html.twig'], $webUi['templates']);
        self::assertSame(['ROLE_SEO'], $security['access_roles']);
    }

    public function testLegacyAdminKeysAreDeprecated(): void
    {
        $tree = (new Configuration())->getConfigTreeBuilder()->buildTree();
        self::assertInstanceOf(ArrayNode::class, $tree);
        $admin = $tree->getChildren()['admin'];
        self::assertInstanceOf(ArrayNode::class, $admin);
        foreach ($admin->getChildren() as $name => $node) {
            self::assertInstanceOf(BaseNode::class, $node);
            self::assertTrue($node->isDeprecated(), $name);
            self::assertStringContainsString('UPGRADING', $node->getDeprecation($name, 'nowo_seo_kit.admin')['message']);
        }
        $role = $admin->getChildren()['role'];
        self::assertInstanceOf(BaseNode::class, $role);
        self::assertSame('nowo_seo_kit.security.access_roles', explode('"', $role->getDeprecation('role', 'nowo_seo_kit.admin')['message'])[3]);
    }

    public function testLegacyPublicRoleIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('would open the admin UI to everybody');
        SeoKitExtension::resolveWebUi((new Processor())->processConfiguration(new Configuration(), [[
            'admin' => ['enabled' => true, 'role' => 'PUBLIC_ACCESS'],
        ]]));
    }

    public function testSecuritySwitchesMustBeLiteralBooleans(): void
    {
        // What the extension sees for `allow_unauthenticated: '%env(bool:SEO_PUBLIC_ADMIN)%'`: a placeholder string.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('security.allow_unauthenticated must be a literal boolean');
        SeoKitExtension::resolveWebUi([
            'web_ui'   => ['enabled' => true, 'settings' => true, 'surfaces' => true, 'path_prefix' => null, 'layout_template' => null, 'templates' => SeoKitPages::TEMPLATES, 'form_themes' => [], 'api_enabled' => true, 'api_csrf_protection' => true],
            'security' => ['access_roles' => ['ROLE_ADMIN'], 'access_checker' => null, 'allow_unauthenticated' => 'env_0123_bool_SEO_PUBLIC_ADMIN'],
        ]);
    }
}
