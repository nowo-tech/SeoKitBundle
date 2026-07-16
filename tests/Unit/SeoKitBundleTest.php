<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit;

use Nowo\SeoKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\SeoKitBundle\DependencyInjection\SeoKitExtension;
use Nowo\SeoKitBundle\SeoKitBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SeoKitBundleTest extends TestCase
{
    public function testBuildRegistersTwigPathsPass(): void
    {
        $container = new ContainerBuilder();
        $bundle    = new SeoKitBundle();
        $bundle->build($container);

        $found = false;
        foreach ($container->getCompilerPassConfig()->getPasses() as $pass) {
            if ($pass instanceof TwigPathsPass) {
                $found = true;
                break;
            }
        }

        self::assertTrue($found);
    }

    public function testGetContainerExtensionReturnsSeoKitExtension(): void
    {
        $bundle = new SeoKitBundle();

        self::assertInstanceOf(SeoKitExtension::class, $bundle->getContainerExtension());
    }
}
