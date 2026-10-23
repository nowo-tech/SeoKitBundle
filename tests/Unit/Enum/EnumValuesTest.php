<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Enum;

use Nowo\SeoKitBundle\Enum\SitemapChangeFreq;
use Nowo\SeoKitBundle\Enum\TwitterCard;
use PHPUnit\Framework\TestCase;

final class EnumValuesTest extends TestCase
{
    public function testSitemapChangeFreqValues(): void
    {
        self::assertSame(['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'], SitemapChangeFreq::values());
    }

    public function testTwitterCardValues(): void
    {
        self::assertSame(['summary', 'summary_large_image', 'app', 'player'], TwitterCard::values());
    }
}
