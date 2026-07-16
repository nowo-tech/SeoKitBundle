<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\SeoKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use PHPUnit\Framework\TestCase;
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
            [['addPath', [dirname(__DIR__, 4) . '/src/Resources/views', 'NowoSeoKit']]],
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
            [['addPath', [dirname(__DIR__, 4) . '/src/Resources/views', 'NowoSeoKit']]],
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
