<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Support;

use Nowo\Brand\Contract\Brand;
use Nowo\Brand\Contract\BrandProviderInterface;

/**
 * Returns a fixed brand and counts the calls (spec 220 S6 tests).
 */
final class CountingBrandProvider implements BrandProviderInterface
{
    public int $calls = 0;

    public function __construct(private readonly Brand $brand)
    {
    }

    public function getBrand(): Brand
    {
        ++$this->calls;

        return $this->brand;
    }
}
