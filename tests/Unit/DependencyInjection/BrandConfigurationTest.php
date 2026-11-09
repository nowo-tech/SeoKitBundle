<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\DependencyInjection;

use Nowo\Brand\Contract\BrandProviderInterface;
use Nowo\SeoKitBundle\Brand\SeoKitBrand;
use Nowo\SeoKitBundle\DependencyInjection\Configuration;
use Nowo\SeoKitBundle\DependencyInjection\SeoKitExtension;
use Nowo\SeoKitBundle\Service\ConfigPageHeadDefaultsProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Spec 220 S6: nowo_seo_kit.brand configuration and wiring.
 */
final class BrandConfigurationTest extends TestCase
{
    public function testDefaultsAreAutoWithoutImages(): void
    {
        self::assertSame(['mode' => 'auto', 'images' => false], $this->process([])['brand']);
    }

    public function testStringShorthandAndImages(): void
    {
        self::assertSame(['mode' => 'off', 'images' => false], $this->process(['brand' => 'off'])['brand']);
        self::assertEquals(['mode' => 'auto', 'images' => true], $this->process(['brand' => ['images' => true]])['brand']);
    }

    public function testUnknownModeIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->process(['brand' => 'always']);
    }

    public function testAutoRegistersTheConsumerWithAnOptionalProvider(): void
    {
        $container = new ContainerBuilder();
        (new SeoKitExtension())->load([['brand' => ['images' => true]]], $container);

        self::assertTrue($container->hasDefinition(SeoKitBrand::class));
        $definition = $container->getDefinition(SeoKitBrand::class);
        $provider   = $definition->getArgument('$provider');
        self::assertInstanceOf(Reference::class, $provider);
        self::assertSame(BrandProviderInterface::class, (string) $provider);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $provider->getInvalidBehavior());
        self::assertTrue($definition->getArgument('$images'));
        self::assertFalse($definition->isPublic());
    }

    public function testOffRegistersNothing(): void
    {
        $container = new ContainerBuilder();
        (new SeoKitExtension())->load([['brand' => 'off', 'base_url' => 'https://example.test']], $container);

        self::assertFalse($container->hasDefinition(SeoKitBrand::class));
    }

    public function testThePageHeadDefaultsGetTheOptionalBrand(): void
    {
        $container = new ContainerBuilder();
        (new SeoKitExtension())->load([['base_url' => 'https://example.test']], $container);

        $brand = $container->getDefinition(ConfigPageHeadDefaultsProvider::class)->getArgument('$brand');
        self::assertInstanceOf(Reference::class, $brand);
        self::assertSame(SeoKitBrand::class, (string) $brand);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $brand->getInvalidBehavior());
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
