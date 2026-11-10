<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Worker;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Nowo\SeoKitBundle\SeoKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function sys_get_temp_dir;

/**
 * DDD-style host: the SeoKit entities on a dedicated "seo" manager (doctrine.entity_manager), the host's own
 * entities on "default".
 */
final class WorkerTestKernel extends Kernel
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
        return sys_get_temp_dir() . '/nowo-seo-kit-worker/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/nowo-seo-kit-worker/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'                => 'worker-test',
            'test'                  => true,
            'http_method_override'  => false,
            'handle_all_throwables' => true,
            'php_errors'            => ['log' => true],
            'router'                => ['utf8' => true],
        ]);
        $container->extension('doctrine', [
            'dbal' => [
                'default_connection' => 'default',
                'connections'        => [
                    'default' => ['url' => 'sqlite:///:memory:'],
                    'seo'     => ['url' => 'sqlite:///:memory:'],
                ],
            ],
            'orm' => [
                'default_entity_manager' => 'default',
                'entity_managers'        => [
                    'default' => ['connection' => 'default'],
                    'seo'     => ['connection' => 'seo'],
                ],
            ],
        ]);
        $container->extension('nowo_seo_kit', [
            'base_url'       => 'https://example.test',
            'default_locale' => 'en',
            'locales'        => ['en'],
            'pages'          => ['app_home' => ['title' => 'Home', 'path' => '/', 'in_sitemap' => true]],
            'persistence'    => ['enabled' => true],
            'doctrine'       => ['entity_manager' => 'seo'],
        ]);
        $container->services()->alias('test.worker.seo_surface_repository', SeoSurfaceRepository::class)->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'nowo_seo_kit');
    }
}
