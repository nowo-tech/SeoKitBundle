<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Support;

use Nowo\Brand\Contract\Brand;
use Nowo\Brand\Contract\BrandAsset;
use Nowo\Brand\Contract\BrandAssetOrigin;
use Nowo\Brand\Contract\BrandAssetSlot;
use Nowo\Brand\Contract\BrandColors;
use Nowo\Brand\Contract\BrandProviderInterface;
use RuntimeException;

use function hash;

/**
 * Brand-contract values for the spec 220 S6 tests.
 */
final class BrandFixtures
{
    /**
     * @param array<string, BrandAsset> $assets
     */
    public static function brand(string $name = 'Acme Portal', array $assets = [], bool $isDefault = false): Brand
    {
        return new Brand(
            $name,
            new BrandColors('#1f6f54', '#134736', '#b42318', '#b54708', '#f3f6f4', '#0f1c18', '#ffffff'),
            new BrandColors('#4aad7f', '#6bc49a', '#f97066', '#fdb022', '#0c1210', '#e6eee9', '#151c19'),
            $assets,
            isDefault: $isDefault,
        );
    }

    public static function asset(
        string $slot = BrandAssetSlot::LOGO_LIGHT,
        string $url = '/_settings-file/brand/logo_light/0123456789ab.png',
        string $mimeType = 'image/png',
        ?int $width = 1200,
        ?int $height = 400,
        BrandAssetOrigin $origin = BrandAssetOrigin::Uploaded,
    ): BrandAsset {
        return new BrandAsset($slot, $url, $mimeType, $width, $height, hash('sha256', $slot . $url), $origin);
    }

    public static function mark(string $url = '/_settings-file/brand/mark/abcdef012345.png'): BrandAsset
    {
        return self::asset(BrandAssetSlot::MARK, $url, 'image/png', 512, 512);
    }

    public static function provider(Brand $brand): CountingBrandProvider
    {
        return new CountingBrandProvider($brand);
    }

    public static function failingProvider(): BrandProviderInterface
    {
        return new class implements BrandProviderInterface {
            public function getBrand(): Brand
            {
                throw new RuntimeException('database unavailable');
            }
        };
    }
}
