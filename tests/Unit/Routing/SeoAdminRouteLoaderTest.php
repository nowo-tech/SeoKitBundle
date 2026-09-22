<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Routing;

use Nowo\SeoKitBundle\Routing\SeoAdminRouteLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Loader\AttributeFileLoader;
use Symfony\Component\Routing\Route;

final class SeoAdminRouteLoaderTest extends TestCase
{
    public function testSupportsOnlyAdminType(): void
    {
        $loader = new SeoAdminRouteLoader();

        self::assertTrue($loader->supports('.', 'nowo_seo_kit_admin'));
        self::assertFalse($loader->supports('.', 'nowo_seo_kit'));
        self::assertFalse($loader->supports('.'));
    }

    public function testLoadReturnsEmptyWhenAdminDisabled(): void
    {
        $loader     = new SeoAdminRouteLoader(false);
        $collection = $loader->load('.', 'nowo_seo_kit_admin');

        self::assertCount(0, $collection);
    }

    public function testLoadImportsControllersWhenEnabled(): void
    {
        $loader     = $this->resolvedLoader(true, true, true, true);
        $collection = $loader->load('.', 'nowo_seo_kit_admin');

        self::assertTrue($collection->get('nowo_seo_kit_admin_settings') instanceof Route);
        self::assertTrue($collection->get('nowo_seo_kit_admin_surfaces') instanceof Route);
        self::assertTrue($collection->get('nowo_seo_kit_api_surface_get') instanceof Route);
    }

    public function testLoadCanDisableSubfeatures(): void
    {
        $loader     = $this->resolvedLoader(true, false, false, false);
        $collection = $loader->load('.', 'nowo_seo_kit_admin');

        self::assertNull($collection->get('nowo_seo_kit_admin_settings'));
        self::assertNull($collection->get('nowo_seo_kit_admin_surfaces'));
        self::assertNull($collection->get('nowo_seo_kit_api_surface_get'));
    }

    public function testLoadTwiceThrows(): void
    {
        $loader = new SeoAdminRouteLoader(false);
        $loader->load('.', 'nowo_seo_kit_admin');

        $this->expectException(RuntimeException::class);
        $loader->load('.', 'nowo_seo_kit_admin');
    }

    private function resolvedLoader(
        bool $admin,
        bool $settings,
        bool $surfaces,
        bool $api,
    ): SeoAdminRouteLoader {
        $locator     = new FileLocator();
        $classLoader = new AttributeRouteControllerLoader();
        $fileLoader  = new AttributeFileLoader($locator, $classLoader);
        $dirLoader   = new AttributeDirectoryLoader($locator, $classLoader);
        $loader      = new SeoAdminRouteLoader($admin, $settings, $surfaces, $api);
        $loader->setResolver(new LoaderResolver([$loader, $fileLoader, $dirLoader]));

        return $loader;
    }
}
