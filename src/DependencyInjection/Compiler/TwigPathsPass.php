<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function dirname;

/**
 * Ensures Twig can resolve @NowoSeoKit/… templates (REQ-TWIG-001).
 */
final class TwigPathsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('twig.loader.native_filesystem')
            && !$container->hasDefinition('twig.loader.filesystem')) {
            return;
        }

        $path = dirname(__DIR__, 2) . '/Resources/views';
        $id   = $container->hasDefinition('twig.loader.native_filesystem')
            ? 'twig.loader.native_filesystem'
            : 'twig.loader.filesystem';

        $container->getDefinition($id)->addMethodCall('addPath', [$path, 'NowoSeoKit']);
    }
}
