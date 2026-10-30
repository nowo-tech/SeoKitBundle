<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Service\OriginUrlGuard;
use PHPUnit\Framework\TestCase;

final class OriginUrlGuardTest extends TestCase
{
    public function testCanonicalMustStayOnOrigin(): void
    {
        $guard = new OriginUrlGuard('https://nowo.tech');

        self::assertTrue($guard->isSafeCanonical('/es/pricing'));
        self::assertTrue($guard->isSafeCanonical('https://nowo.tech/es/pricing'));
        self::assertFalse($guard->isSafeCanonical('https://evil.example/'));
        self::assertFalse($guard->isSafeCanonical('//evil.example/'));
    }

    public function testOpenGraphImageAllowsHttpsAndPathsOnly(): void
    {
        $guard = new OriginUrlGuard('https://nowo.tech');

        self::assertTrue($guard->isSafeOpenGraphImage('/brand/og.png'));
        self::assertTrue($guard->isSafeOpenGraphImage('https://cdn.example/og.png'));
        self::assertFalse($guard->isSafeOpenGraphImage('http://cdn.example/og.png'));
        self::assertFalse($guard->isSafeOpenGraphImage('//cdn.example/og.png'));
        self::assertTrue($guard->isSafeOpenGraphImage(null));
        self::assertTrue($guard->isSafeOpenGraphImage('  '));
    }

    public function testWithoutOriginOnlyRelativePathsAreSafe(): void
    {
        $guard = new OriginUrlGuard();

        self::assertNull($guard->origin());
        self::assertTrue($guard->belongsToSite('/path'));
        self::assertFalse($guard->belongsToSite('https://nowo.tech/path'));
        self::assertTrue($guard->isSafeCanonical('/path'));
        self::assertFalse($guard->isSafeCanonical('https://nowo.tech/path'));
        self::assertFalse($guard->isSafeCanonical('C:\\windows'));
    }

    public function testBelongsToSiteExactOrigin(): void
    {
        $guard = new OriginUrlGuard('https://nowo.tech');

        self::assertSame('https://nowo.tech', $guard->origin());
        self::assertTrue($guard->belongsToSite('https://nowo.tech'));
        self::assertTrue($guard->belongsToSite('https://nowo.tech/x'));
        self::assertTrue($guard->isSafeCanonical(null));
    }

    public function testInnerControlCharactersAndWhitespaceAreRejected(): void
    {
        $guard = new OriginUrlGuard('https://nowo.tech');

        foreach (["/\t/evil.example", "/\n/evil.example", '/a b', "/x\x00y", "/x\x7F", "https://nowo.tech/\r\npath"] as $url) {
            self::assertFalse($guard->isSafeCanonical($url), $url);
            self::assertFalse($guard->isSafeOpenGraphImage($url), $url);
        }
        // surrounding whitespace is trimmed, not rejected
        self::assertTrue($guard->isSafeCanonical("  /pricing\n"));
        self::assertTrue($guard->isSafeOpenGraphImage(' /og.png '));
    }

    public function testOriginWithTrailingSlashStillMatchesItsUrls(): void
    {
        $guard = new OriginUrlGuard('https://nowo.tech/');

        self::assertTrue($guard->isSafeCanonical('https://nowo.tech/es/pricing'));
        self::assertTrue($guard->belongsToSite('https://nowo.tech'));
        self::assertFalse($guard->isSafeCanonical('https://nowo.tech.evil.example/'));
    }
}
