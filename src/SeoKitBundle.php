<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle;

use Nowo\SeoKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\SeoKitBundle\DependencyInjection\SeoKitExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Multilingual SEO kit for Symfony (meta, hreflang, sitemap, robots).
 */
final class SeoKitBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new TwigPathsPass());
    }

    public function getContainerExtension(): ExtensionInterface
    {
        return new SeoKitExtension();
    }
}
