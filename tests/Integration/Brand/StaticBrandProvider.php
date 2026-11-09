<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Brand;

use Nowo\Brand\Contract\Brand;
use Nowo\Brand\Contract\BrandAssetSlot;
use Nowo\Brand\Contract\BrandProviderInterface;
use Nowo\SeoKitBundle\Tests\Support\BrandFixtures;

/**
 * Host brand provider of the integration kernel.
 */
final class StaticBrandProvider implements BrandProviderInterface
{
    public function getBrand(): Brand
    {
        return BrandFixtures::brand('Acme Portal', [BrandAssetSlot::LOGO_LIGHT => BrandFixtures::asset(), BrandAssetSlot::MARK => BrandFixtures::mark()]);
    }
}
