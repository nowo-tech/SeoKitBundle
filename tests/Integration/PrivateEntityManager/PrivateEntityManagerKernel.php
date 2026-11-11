<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\PrivateEntityManager;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\SeoKitBundle\Doctrine\SeoKitEntityManager;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Nowo\SeoKitBundle\SeoKitBundle;
use Nowo\SeoKitBundle\Service\SeoSurfaceManagerInterface;
use Nowo\SeoKitBundle\Service\SeoSurfaceStoreInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function sys_get_temp_dir;

/**
 * Standard host (zero config, REQ-ARCH-001 rule 6): one default entity manager on one connection maps both the host's
 * domain (XML) and the SeoKit entities; `doctrine.entity_manager` is not set, so the bundle writes through its private
 * entity manager. The host has a tenant SQL filter.
 */
final class PrivateEntityManagerKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle(), new DoctrineBundle(), new SeoKitBundle()];
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/nowo-seo-kit-private-em/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/nowo-seo-kit-private-em/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'                => 'private-em-test',
            'test'                  => true,
            'http_method_override'  => false,
            'handle_all_throwables' => true,
            'php_errors'            => ['log' => true],
            'router'                => ['utf8' => true],
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm'  => [
                'entity_managers' => [
                    'default' => [
                        'mappings' => [
                            'Host' => [
                                'type'      => 'xml',
                                'dir'       => __DIR__ . '/../Ddd/mapping',
                                'prefix'    => 'Nowo\\SeoKitBundle\\Tests\\Integration\\Ddd\\Domain',
                                'is_bundle' => false,
                            ],
                        ],
                        'filters' => ['tenant' => ['class' => TenantFilter::class, 'enabled' => false]],
                    ],
                ],
            ],
        ]);
        $container->extension('nowo_seo_kit', [
            'base_url'       => 'https://example.test',
            'default_locale' => 'en',
            'locales'        => ['en'],
            'persistence'    => ['enabled' => true],
        ]);

        $services = $container->services();
        $services->defaults()->autowire()->autoconfigure();
        $services->set('logger', NullLogger::class);
        $services->set(SurfaceTitleController::class)->public()->tag('controller.service_arguments');
        $services->alias('test.pem.store', SeoSurfaceStoreInterface::class)->public();
        $services->alias('test.pem.manager', SeoSurfaceManagerInterface::class)->public();
        $services->alias('test.pem.surfaces', SeoSurfaceRepository::class)->public();
        $services->alias('test.pem.settings', SeoSiteSettingsRepository::class)->public();
        $services->alias('test.pem.private', SeoKitEntityManager::class)->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'nowo_seo_kit');
        $routes->add('test_surface_title', '/_test/surface-title')->controller(SurfaceTitleController::class);
    }
}
