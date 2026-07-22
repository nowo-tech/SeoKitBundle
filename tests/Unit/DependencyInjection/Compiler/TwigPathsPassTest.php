<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\SeoKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function dirname;

final class TwigPathsPassTest extends TestCase
{
    public function testProcessAddsTwigNamespaceWhenNativeFilesystemLoaderExists(): void
    {
        $container = new ContainerBuilder();
        $loader    = new Definition(\Twig\Loader\FilesystemLoader::class);
        $container->setDefinition('twig.loader.native_filesystem', $loader);

        (new TwigPathsPass())->process($container);

        self::assertSame(
            [['addPath', [dirname(__DIR__, 4) . '/src/Resources/views', 'NowoSeoKitBundle']]],
            $loader->getMethodCalls(),
        );
    }

    public function testProcessPrependsOverridePathWhenPresent(): void
    {
        $container  = new ContainerBuilder();
        $loader     = new Definition(\Twig\Loader\FilesystemLoader::class);
        $projectDir = sys_get_temp_dir() . '/seo-kit-twig-paths-' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/templates/bundles/NowoSeoKitBundle', 0777, true);

        $container->setDefinition('twig.loader.native_filesystem', $loader);
        $container->setParameter('kernel.project_dir', $projectDir);

        try {
            (new TwigPathsPass())->process($container);
        } finally {
            rmdir($projectDir . '/templates/bundles/NowoSeoKitBundle');
            rmdir($projectDir . '/templates/bundles');
            rmdir($projectDir . '/templates');
            rmdir($projectDir);
        }

        self::assertSame(
            [
                ['prependPath', [$projectDir . '/templates/bundles/NowoSeoKitBundle', 'NowoSeoKitBundle']],
                ['addPath', [dirname(__DIR__, 4) . '/src/Resources/views', 'NowoSeoKitBundle']],
            ],
            $loader->getMethodCalls(),
        );
    }

    public function testProcessUsesNativeLoaderAlias(): void
    {
        $container = new ContainerBuilder();
        $loader    = new Definition(\Twig\Loader\FilesystemLoader::class);
        $container->setDefinition('twig.loader.native_filesystem', $loader);
        $container->setAlias('twig.loader.native.mid', new Alias('twig.loader.native_filesystem'));
        $container->setAlias('twig.loader.native', new Alias('twig.loader.native.mid'));

        (new TwigPathsPass())->process($container);

        self::assertSame(
            [['addPath', [dirname(__DIR__, 4) . '/src/Resources/views', 'NowoSeoKitBundle']]],
            $loader->getMethodCalls(),
        );
    }

    public function testProcessUsesNativeLoaderDefinition(): void
    {
        $container = new ContainerBuilder();
        $loader    = new Definition(\Twig\Loader\FilesystemLoader::class);
        $container->setDefinition('twig.loader.native', $loader);

        (new TwigPathsPass())->process($container);

        self::assertSame(
            [['addPath', [dirname(__DIR__, 4) . '/src/Resources/views', 'NowoSeoKitBundle']]],
            $loader->getMethodCalls(),
        );
    }

    public function testProcessFallsBackWhenNativeAliasTargetMissing(): void
    {
        $container = new ContainerBuilder();
        $loader    = new Definition(\Twig\Loader\FilesystemLoader::class);
        $container->setAlias('twig.loader.native', new Alias('twig.loader.missing'));
        $container->setDefinition('twig.loader.filesystem', $loader);

        (new TwigPathsPass())->process($container);

        self::assertSame(
            [['addPath', [dirname(__DIR__, 4) . '/src/Resources/views', 'NowoSeoKitBundle']]],
            $loader->getMethodCalls(),
        );
    }

    public function testProcessUsesFilesystemLoaderWhenNativeLoaderMissing(): void
    {
        $container = new ContainerBuilder();
        $loader    = new Definition(\Twig\Loader\FilesystemLoader::class);
        $container->setDefinition('twig.loader.filesystem', $loader);

        (new TwigPathsPass())->process($container);

        self::assertSame(
            [['addPath', [dirname(__DIR__, 4) . '/src/Resources/views', 'NowoSeoKitBundle']]],
            $loader->getMethodCalls(),
        );
    }

    public function testProcessNoopsWhenTwigLoaderMissing(): void
    {
        $container = new ContainerBuilder();

        (new TwigPathsPass())->process($container);

        self::assertFalse($container->hasDefinition('twig.loader.filesystem'));
    }
}
