<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\RouterInterface;

use function array_filter;
use function array_keys;
use function array_values;
use function class_exists;
use function str_starts_with;
use function sys_get_temp_dir;

/**
 * Symfony >= 7.3 autoconfigures services whose class carries #[Route] with `routing.controller`, and the host's default
 * `config/routes.yaml` (`resource: routing.controllers`) loads them. The bundle controllers must not be registered that
 * way: their routes only exist through the opt-in `nowo_seo_kit_admin` loader (web_ui.enabled, path_prefix).
 */
final class SeoKitHostControllersRoutingTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(AttributeServicesLoader::class)) {
            self::markTestSkipped('symfony/routing < 7.3 has no routing.controllers loader.');
        }
        (new Filesystem())->remove(sys_get_temp_dir() . SeoKitAdminTestKernel::DIR);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir() . SeoKitAdminTestKernel::DIR);
    }

    public function testHostRoutingControllersDoesNotLoadTheBundleRoutes(): void
    {
        $kernel = new SeoKitAdminTestKernel('test', false, true);
        $kernel->boot();

        try {
            $router = $kernel->getContainer()->get('router');
            self::assertInstanceOf(RouterInterface::class, $router);
            $names = array_keys($router->getRouteCollection()->all());

            self::assertSame([], array_values(array_filter($names, static fn (string $name): bool => str_starts_with($name, 'nowo_seo_kit_'))));
        } finally {
            $kernel->shutdown();
        }
    }
}
