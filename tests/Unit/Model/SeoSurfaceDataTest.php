<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Model;

use InvalidArgumentException;
use Nowo\SeoKitBundle\Model\SeoSurfaceData;
use PHPUnit\Framework\TestCase;

final class SeoSurfaceDataTest extends TestCase
{
    public function testNormalisesLikeTheEntity(): void
    {
        $data = new SeoSurfaceData(' page:home ', ' EN ', ' Home ', '  ', null, ' https://example.test/ ', '', []);

        self::assertSame('page:home', $data->surfaceKey);
        self::assertSame('en', $data->locale);
        self::assertSame('Home', $data->metaTitle);
        self::assertNull($data->metaDescription);
        self::assertNull($data->metaRobots);
        self::assertSame('https://example.test/', $data->canonicalOverride);
        self::assertNull($data->openGraphImage);
        self::assertNull($data->structuredDataExtra);
        self::assertFalse($data->isEmpty());
    }

    public function testIsEmptyOnlyWhenEveryOverrideIsEmpty(): void
    {
        self::assertTrue((new SeoSurfaceData('page:home', 'en', ' '))->isEmpty());
        self::assertFalse((new SeoSurfaceData('page:home', 'en', metaRobots: 'noindex'))->isEmpty());
        self::assertFalse((new SeoSurfaceData('page:home', 'en', structuredDataExtra: ['@type' => 'WebPage']))->isEmpty());
        self::assertFalse((new SeoSurfaceData('page:home', 'en', metaDescription: 'D'))->isEmpty());
        self::assertFalse((new SeoSurfaceData('page:home', 'en', openGraphImage: '/og.png'))->isEmpty());
        self::assertFalse((new SeoSurfaceData('page:home', 'en', canonicalOverride: '/c'))->isEmpty());
    }

    public function testRejectsAnEmptyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SeoSurfaceData(' ', 'en');
    }

    public function testRejectsAnEmptyLocale(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SeoSurfaceData('page:home', ' ');
    }
}
