<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\Brand\Contract\BrandAssetOrigin;
use Nowo\Brand\Contract\BrandAssetSlot;
use Nowo\SeoKitBundle\Brand\SeoKitBrand;
use Nowo\SeoKitBundle\Service\AbsoluteUrlBuilder;
use Nowo\SeoKitBundle\Service\ConfigPageHeadDefaultsProvider;
use Nowo\SeoKitBundle\Tests\Support\BrandFixtures;
use PHPUnit\Framework\TestCase;

/**
 * Spec 220 S6 on the PageHead path: the brand fills an empty site name and, opt-in, an empty og:image.
 */
final class ConfigPageHeadDefaultsProviderBrandTest extends TestCase
{
    public function testWithoutBrandOrWithAFailingProviderNothingChanges(): void
    {
        $config   = ['defaults' => ['title' => 'Home']];
        $baseline = (new ConfigPageHeadDefaultsProvider($config))->forLocale('en');

        self::assertEquals($baseline, (new ConfigPageHeadDefaultsProvider($config, new SeoKitBrand(images: true), $this->urls()))->forLocale('en'));
        self::assertEquals($baseline, (new ConfigPageHeadDefaultsProvider($config, new SeoKitBrand(BrandFixtures::failingProvider(), images: true), $this->urls()))->forLocale('en'));
    }

    public function testTheBrandNameFillsAnEmptySiteNameAndTheDefaultTitle(): void
    {
        $defaults = (new ConfigPageHeadDefaultsProvider([], $this->brand(), $this->urls()))->forLocale('en');

        self::assertSame('Acme Portal', $defaults->siteName);
        self::assertSame('Acme Portal', $defaults->title);
        self::assertNull($defaults->ogImage, 'Images are opt-in.');
    }

    public function testConfiguredValuesWin(): void
    {
        $defaults = (new ConfigPageHeadDefaultsProvider([
            'defaults' => [
                'site_name'  => 'Configured',
                'open_graph' => ['image' => 'https://example.test/og.png'],
            ],
        ], $this->brand(images: true), $this->urls()))->forLocale('en');

        self::assertSame('Configured', $defaults->siteName);
        self::assertSame('https://example.test/og.png', $defaults->ogImage);
        self::assertSame(1200, $defaults->ogImageWidth);
        self::assertSame(630, $defaults->ogImageHeight);
    }

    public function testTheLogoIsTheFallbackOgImageOnTheBaseUrlOrigin(): void
    {
        $defaults = (new ConfigPageHeadDefaultsProvider([], $this->brand(images: true), $this->urls()))->forLocale('en');

        self::assertSame('https://example.test/_settings-file/brand/logo_light/0123456789ab.png', $defaults->ogImage);
        self::assertSame(1200, $defaults->ogImageWidth);
        self::assertSame(400, $defaults->ogImageHeight);
    }

    public function testNoImageWithoutAnUrlBuilderOrForASvg(): void
    {
        self::assertNull((new ConfigPageHeadDefaultsProvider([], $this->brand(images: true)))->forLocale('en')->ogImage);

        $svg   = BrandFixtures::asset(BrandAssetSlot::LOGO_LIGHT, '/brand/logo.svg', 'image/svg+xml', null, null, BrandAssetOrigin::ConfigDefault);
        $brand = new SeoKitBrand(BrandFixtures::provider(BrandFixtures::brand(assets: [BrandAssetSlot::LOGO_LIGHT => $svg])), images: true);
        self::assertNull((new ConfigPageHeadDefaultsProvider([], $brand, $this->urls()))->forLocale('en')->ogImage);
    }

    private function brand(bool $images = false): SeoKitBrand
    {
        return new SeoKitBrand(BrandFixtures::provider(BrandFixtures::brand(assets: [BrandAssetSlot::LOGO_LIGHT => BrandFixtures::asset()])), images: $images);
    }

    private function urls(): AbsoluteUrlBuilder
    {
        return new AbsoluteUrlBuilder('https://example.test');
    }
}
