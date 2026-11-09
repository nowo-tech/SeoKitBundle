<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Brand;

use Nowo\Brand\Contract\BrandAssetOrigin;
use Nowo\Brand\Contract\BrandAssetSlot;
use Nowo\SeoKitBundle\Brand\SeoKitBrand;
use Nowo\SeoKitBundle\Tests\Support\BrandFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

final class SeoKitBrandTest extends TestCase
{
    public function testWithoutProviderThereIsNoBrand(): void
    {
        self::assertNull((new SeoKitBrand())->current());
    }

    public function testCurrentAsksTheProviderOnEveryCall(): void
    {
        $provider = BrandFixtures::provider(BrandFixtures::brand());
        $brand    = new SeoKitBrand($provider);

        $first  = $brand->current();
        $second = $brand->current();

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame('Acme Portal', $first->name);
        self::assertSame(2, $provider->calls, 'No memo: a worker keeps nothing between requests.');
    }

    public function testAFailingProviderIsLoggedAndYieldsNoBrand(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{string, string}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message];
            }
        };

        self::assertNull((new SeoKitBrand(BrandFixtures::failingProvider(), logger: $logger))->current());
        self::assertSame([['warning', 'SeoKitBundle: the brand provider failed, using the nowo_seo_kit configuration.']], $logger->records);
        self::assertNull((new SeoKitBrand(BrandFixtures::failingProvider()))->current(), 'No logger: still no exception.');
    }

    public function testImagesAreOffByDefault(): void
    {
        $brand = BrandFixtures::brand(assets: [BrandAssetSlot::LOGO_LIGHT => BrandFixtures::asset(), BrandAssetSlot::MARK => BrandFixtures::mark()]);
        $seo   = new SeoKitBrand();

        self::assertFalse($seo->usesImages());
        self::assertNull($seo->ogImage($brand));
        self::assertNull($seo->logo($brand));
    }

    public function testOgImagePrefersTheLockupAndTheLogoPrefersTheMark(): void
    {
        $logo  = BrandFixtures::asset();
        $mark  = BrandFixtures::mark();
        $brand = BrandFixtures::brand(assets: [BrandAssetSlot::LOGO_LIGHT => $logo, BrandAssetSlot::MARK => $mark]);
        $seo   = new SeoKitBrand(images: true);

        self::assertTrue($seo->usesImages());
        self::assertSame($logo, $seo->ogImage($brand));
        self::assertSame($mark, $seo->logo($brand));

        $onlyMark = BrandFixtures::brand(assets: [BrandAssetSlot::MARK => $mark]);
        self::assertSame($mark, $seo->ogImage($onlyMark));

        $onlyLogo = BrandFixtures::brand(assets: [BrandAssetSlot::LOGO_LIGHT => $logo]);
        self::assertSame($logo, $seo->logo($onlyLogo));

        self::assertNull($seo->ogImage(BrandFixtures::brand()));
        self::assertNull($seo->logo(BrandFixtures::brand()));
    }

    public function testTheMarkFallsBackToTheAppIconThroughTheContractChain(): void
    {
        $icon  = BrandFixtures::asset(BrandAssetSlot::APP_ICON, '/_settings-file/brand/app_icon/aaaaaaaaaaaa.png', 'image/png', 512, 512);
        $brand = BrandFixtures::brand(assets: [BrandAssetSlot::APP_ICON => $icon]);

        self::assertSame($icon, (new SeoKitBrand(images: true))->logo($brand));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function nonRasterProvider(): iterable
    {
        yield 'svg' => ['/brand/logo.svg', 'image/svg+xml'];
        yield 'ico' => ['/brand/favicon.ico', 'image/x-icon'];
        yield 'gif' => ['/brand/logo.gif', 'image/gif'];
    }

    #[DataProvider('nonRasterProvider')]
    public function testSvgAndOtherNonRasterDefaultsAreNeverUsed(string $url, string $mimeType): void
    {
        $logo  = BrandFixtures::asset(BrandAssetSlot::LOGO_LIGHT, $url, $mimeType, null, null, BrandAssetOrigin::ConfigDefault);
        $mark  = BrandFixtures::asset(BrandAssetSlot::MARK, $url, $mimeType, null, null, BrandAssetOrigin::ConfigDefault);
        $brand = BrandFixtures::brand(assets: [BrandAssetSlot::LOGO_LIGHT => $logo, BrandAssetSlot::MARK => $mark]);
        $seo   = new SeoKitBrand(images: true);

        self::assertFalse(SeoKitBrand::isUsableImage($logo));
        self::assertNull($seo->ogImage($brand));
        self::assertNull($seo->logo($brand));
    }

    public function testASvgLockupFallsThroughToARasterMark(): void
    {
        $svg   = BrandFixtures::asset(BrandAssetSlot::LOGO_LIGHT, '/brand/logo-light.svg', 'image/svg+xml', null, null, BrandAssetOrigin::ConfigDefault);
        $mark  = BrandFixtures::mark();
        $brand = BrandFixtures::brand(assets: [BrandAssetSlot::LOGO_LIGHT => $svg, BrandAssetSlot::MARK => $mark]);

        self::assertSame($mark, (new SeoKitBrand(images: true))->ogImage($brand));
    }

    public function testAbsoluteUrlKeepsHttpsAndPrefixesRootRelativePaths(): void
    {
        $cdn = BrandFixtures::asset(url: 'https://cdn.example.test/brand/logo.png');

        self::assertSame('https://cdn.example.test/brand/logo.png', SeoKitBrand::absoluteUrl($cdn, 'https://www.example.test'));
        self::assertSame('https://cdn.example.test/brand/logo.png', SeoKitBrand::absoluteUrl($cdn, ''), 'An https asset needs no origin.');
        self::assertSame(
            'https://www.example.test/_settings-file/brand/logo_light/0123456789ab.png',
            SeoKitBrand::absoluteUrl(BrandFixtures::asset(), 'https://www.example.test'),
        );
        self::assertSame(
            'http://localhost:8000/_settings-file/brand/logo_light/0123456789ab.png',
            SeoKitBrand::absoluteUrl(BrandFixtures::asset(), 'http://localhost:8000'),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badOriginProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'relative' => ['/sub'];
        yield 'scheme-relative' => ['//example.test'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'ftp' => ['ftp://example.test'];
        yield 'with path' => ['https://example.test/sub'];
        yield 'trailing slash' => ['https://example.test/'];
        yield 'with query' => ['https://example.test?x=1'];
        yield 'with fragment' => ['https://example.test#x'];
        yield 'with credentials' => ['https://user:pass@example.test'];
        yield 'quote' => ['https://example.test"onerror=x'];
        yield 'angle bracket' => ['https://example.test<x'];
        yield 'space' => ['https://exa mple.test'];
        yield 'no host' => ['https://'];
        yield 'port only' => ['https://:443'];
    }

    #[DataProvider('badOriginProvider')]
    public function testARootRelativeAssetOnABadOriginIsRefused(string $origin): void
    {
        self::assertNull(SeoKitBrand::absoluteUrl(BrandFixtures::asset(), $origin));
    }
}
