<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Ddd;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\SeoKitBundle\SeoKitBundle;
use Nowo\SeoKitBundle\Service\SeoSurfaceStoreInterface;
use Nowo\SeoKitBundle\Service\SeoUrlBuilderInterface;
use Nowo\SeoKitBundle\Service\SitemapGenerator;
use Nowo\SeoKitBundle\Tests\Integration\Ddd\Domain\PublishedArticles;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function sys_get_temp_dir;

/**
 * DDD / hexagonal test host (REQ-ARCH-001): its domain object is mapped in XML on its own "default" manager, the
 * SeoKit entities live on a dedicated "seo" manager, and its sitemap adapter implements only the framework-free port.
 * No `base_url`, so absolute URLs come from the SitemapContext origin.
 */
final class DddHostKernel extends Kernel
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
        return sys_get_temp_dir() . '/nowo-seo-kit-ddd/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/nowo-seo-kit-ddd/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'                => 'ddd-host-test',
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
                    'default' => [
                        'connection' => 'default',
                        'mappings'   => [
                            'Host' => [
                                'type'      => 'xml',
                                'dir'       => '%kernel.project_dir%/mapping',
                                'prefix'    => 'Nowo\\SeoKitBundle\\Tests\\Integration\\Ddd\\Domain',
                                'is_bundle' => false,
                            ],
                        ],
                    ],
                    'seo' => ['connection' => 'seo'],
                ],
            ],
        ]);
        $container->extension('nowo_seo_kit', [
            'default_locale' => 'en',
            'locales'        => ['en'],
            'pages'          => ['app_home' => ['title' => 'Home', 'path' => '/', 'in_sitemap' => true]],
            'persistence'    => ['enabled' => true],
            'doctrine'       => ['entity_manager' => 'seo'],
        ]);

        $services = $container->services();
        $services->defaults()->autowire()->autoconfigure();
        $services->set('logger', NullLogger::class);
        $services->set(PublishedArticles::class);
        $services->set(ArticleSitemapProvider::class);
        $services->alias('test.ddd.surface_store', SeoSurfaceStoreInterface::class)->public();
        $services->alias('test.ddd.url_builder', SeoUrlBuilderInterface::class)->public();
        $services->alias('test.ddd.sitemap_generator', SitemapGenerator::class)->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'nowo_seo_kit');
    }
}
