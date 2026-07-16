<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\DependencyInjection;

use Nowo\SeoKitBundle\DependencyInjection\Configuration;
use Nowo\SeoKitBundle\DependencyInjection\SeoKitExtension;
use Nowo\SeoKitBundle\Service\SeoMetadataResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SeoKitExtensionTest extends TestCase
{
    public function testLoadProcessesConfigurationAndRegistersServices(): void
    {
        $container = new ContainerBuilder();
        $extension = new SeoKitExtension();

        $extension->load([['enabled' => true]], $container);

        self::assertSame('nowo_seo_kit', $extension->getAlias());
        self::assertTrue($container->hasParameter(Configuration::ALIAS . '.config'));
        self::assertTrue($container->hasParameter(Configuration::ALIAS . '.enabled'));
        self::assertTrue($container->hasParameter(Configuration::ALIAS . '.templates'));
        self::assertTrue($container->hasDefinition(SeoMetadataResolver::class));
    }
}
