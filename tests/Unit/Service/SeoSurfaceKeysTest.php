<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Service\SeoSurfaceKeys;
use PHPUnit\Framework\TestCase;

final class SeoSurfaceKeysTest extends TestCase
{
    public function testPageAndBlogPrefixes(): void
    {
        self::assertSame('page:home', SeoSurfaceKeys::page('home'));
        self::assertSame('page:home', SeoSurfaceKeys::page('  home  '));
        self::assertSame('blog:42', SeoSurfaceKeys::blog(42));
        self::assertSame('blog:slug', SeoSurfaceKeys::blog('slug'));
        self::assertTrue(SeoSurfaceKeys::isPage('page:home'));
        self::assertFalse(SeoSurfaceKeys::isPage('blog:1'));
        self::assertSame('home', SeoSurfaceKeys::pageKey('page:home'));
        self::assertNull(SeoSurfaceKeys::pageKey('blog:1'));
        self::assertNull(SeoSurfaceKeys::pageKey('page:'));
    }
}
