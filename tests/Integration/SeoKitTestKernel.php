<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration;

use Nowo\SeoKitBundle\SeoKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function sys_get_temp_dir;

/**
 * Minimal Symfony kernel (Framework + Twig + SeoKit) for integration tests (REQ-TEST-002).
 */
final class SeoKitTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle(), new SeoKitBundle()];
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/nowo-seo-kit-integration/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/nowo-seo-kit-integration/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'                => 'integration-test',
            'test'                  => true,
            'http_method_override'  => false,
            'handle_all_throwables' => true,
            'php_errors'            => ['log' => true],
            'router'                => ['utf8' => true],
        ]);
        $container->extension('nowo_seo_kit', [
            'base_url'       => 'https://example.test',
            'default_locale' => 'en',
            'locales'        => ['en', 'es'],
            'pages'          => [
                'app_home' => [
                    'title'      => 'Home',
                    'path'       => '/',
                    'in_sitemap' => true,
                    'locales'    => ['es' => ['title' => 'Inicio', 'path' => '/es']],
                ],
            ],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'nowo_seo_kit');
    }
}
