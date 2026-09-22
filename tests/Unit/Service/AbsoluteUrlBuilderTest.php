<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use InvalidArgumentException;
use Nowo\SeoKitBundle\Service\AbsoluteUrlBuilder;
use PHPUnit\Framework\TestCase;

final class AbsoluteUrlBuilderTest extends TestCase
{
    public function testFromPathJoinsOriginAndNormalisedPath(): void
    {
        $urls = new AbsoluteUrlBuilder('https://nowo.tech/');

        self::assertSame('https://nowo.tech', $urls->origin());
        self::assertSame('https://nowo.tech/', $urls->fromPath('/'));
        self::assertSame('https://nowo.tech/producto/core', $urls->fromPath('/producto/core/'));
        self::assertSame('https://nowo.tech/producto/core', $urls->fromPath('producto/core?x=1#y'));
        self::assertTrue($urls->belongsToSite('https://nowo.tech/es/about'));
        self::assertFalse($urls->belongsToSite('https://evil.example/x'));
    }

    public function testEmptyBaseUrlIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AbsoluteUrlBuilder('  ');
    }

    public function testBaseUrlWithPathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AbsoluteUrlBuilder('https://nowo.tech/app');
    }

    public function testRelativeBaseUrlIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AbsoluteUrlBuilder('nowo.tech');
    }

    public function testEmptyPathNormalisesToRoot(): void
    {
        $urls = new AbsoluteUrlBuilder('https://nowo.tech');

        self::assertSame('https://nowo.tech/', $urls->fromPath(''));
        self::assertTrue($urls->belongsToSite('https://nowo.tech'));
    }
}
