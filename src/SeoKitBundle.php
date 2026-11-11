<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle;

use Nowo\SeoKitBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use Nowo\SeoKitBundle\DependencyInjection\Compiler\EntityManagerOptionPass;
use Nowo\SeoKitBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\SeoKitBundle\DependencyInjection\Compiler\WebUiSecurityPass;
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
        $container->addCompilerPass(new CsrfProtectionPass());
        $container->addCompilerPass(new WebUiSecurityPass());
        $container->addCompilerPass(new TwigPathsPass());
        // 1.23.0 (audit LD-SEO-L1): the entities on doctrine.entity_manager only, never also on an auto-mapped manager.
        $container->addCompilerPass(new EntityManagerOptionPass());
    }

    public function getContainerExtension(): ExtensionInterface
    {
        return new SeoKitExtension();
    }
}
