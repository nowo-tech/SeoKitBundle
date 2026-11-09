<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Brand;

use Nowo\Brand\Contract\BrandProviderInterface;
use Nowo\SeoKitBundle\SeoKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function md5;
use function serialize;
use function sys_get_temp_dir;

/**
 * Real kernel (Twig registered before SeoKit) with or without a host BrandProviderInterface service (spec 220 S6).
 */
final class SeoKitBrandTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $seoConfig
     */
    public function __construct(private readonly bool $withProvider, private readonly array $seoConfig = [])
    {
        parent::__construct('test', false);
    }

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
        return self::baseDir() . '/cache/' . ($this->withProvider ? 'provider' : 'none') . '-' . md5(serialize($this->seoConfig));
    }

    public function getLogDir(): string
    {
        return self::baseDir() . '/log';
    }

    public static function baseDir(): string
    {
        return sys_get_temp_dir() . '/nowo-seo-kit-brand-integration';
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
        $container->extension('nowo_seo_kit', $this->seoConfig + [
            'base_url'       => 'https://example.test',
            'default_locale' => 'en',
            'locales'        => ['en'],
            'pages'          => ['app_home' => ['title' => 'Home', 'path' => '/']],
        ]);

        $services = $container->services();
        if ($this->withProvider) {
            $services->set(BrandProviderInterface::class, StaticBrandProvider::class);
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'nowo_seo_kit');
    }
}
