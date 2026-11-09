<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Brand;

use Nowo\Brand\Contract\Brand;
use Nowo\Brand\Contract\BrandAsset;
use Nowo\Brand\Contract\BrandAssetSlot;
use Nowo\Brand\Contract\BrandProviderInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function in_array;
use function is_string;
use function parse_url;
use function preg_match;
use function str_starts_with;

/**
 * Reads the brand of a `nowo-tech/brand-contracts` {@see BrandProviderInterface} for the head tags (spec 220 S6,
 * SeoKitBundle 1.21).
 *
 * Registered only with `nowo_seo_kit.brand.mode: auto` (the default) and brand-contracts installed; the provider is an
 * optional reference, so without a provider service {@see self::current()} is null and every output stays the
 * `nowo_seo_kit` configuration, byte for byte.
 *
 * From the brand: the name as `defaults.site_name` (and so `og:site_name` and the `{site_name}` title placeholder) and
 * as the JSON-LD Organization `name`, only where the configuration and the defaults providers leave them empty. With
 * `brand.images: true` (opt-in) also the brand logo as the fallback `og:image` / `twitter:image` and as the JSON-LD
 * Organization `logo`, again only where nothing is configured. An image is taken only when it is a raster (PNG, JPEG,
 * WebP: never SVG or ICO), its URL passes {@see BrandAsset::isSafeUrl()} and the absolute URL built from it is a plain
 * http(s) URL; anything else keeps the configuration.
 *
 * Stateless: every call asks the provider again (providers memoise per request themselves), so a long-running worker
 * keeps nothing between requests. A failing provider is logged and the configuration applies; a page never fails
 * because of the brand.
 */
final readonly class SeoKitBrand
{
    /** Slots tried, in order, for the fallback `og:image` (a wide lockup first, then the square mark). */
    public const OG_IMAGE_SLOTS = [BrandAssetSlot::LOGO_LIGHT, BrandAssetSlot::MARK];

    /** Slots tried, in order, for the JSON-LD Organization `logo` (the square mark first, then the lockup). */
    public const LOGO_SLOTS = [BrandAssetSlot::MARK, BrandAssetSlot::LOGO_LIGHT];

    /** Absolute image URL: http(s), a host, printable ASCII without quotes, angle brackets, backslashes or spaces. */
    private const ABSOLUTE_URL_PATTERN = '#^https?://[^\s"\'<>\\\\`]+\z#';

    public function __construct(
        private ?BrandProviderInterface $provider = null,
        private bool $images = false,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * The current brand, or null without a provider or when the provider fails.
     */
    public function current(): ?Brand
    {
        if (!$this->provider instanceof BrandProviderInterface) {
            return null;
        }

        try {
            return $this->provider->getBrand();
        } catch (Throwable $e) {
            $this->logger?->warning('SeoKitBundle: the brand provider failed, using the nowo_seo_kit configuration.', ['exception' => $e]);

            return null;
        }
    }

    /**
     * Whether the brand images (`og:image`, Organization `logo`) are used (`brand.images`).
     */
    public function usesImages(): bool
    {
        return $this->images;
    }

    /**
     * The fallback `og:image` asset (see {@see self::OG_IMAGE_SLOTS}), or null when images are off or no slot holds a
     * usable raster image.
     */
    public function ogImage(Brand $brand): ?BrandAsset
    {
        return $this->firstUsable($brand, self::OG_IMAGE_SLOTS);
    }

    /**
     * The JSON-LD Organization `logo` asset (see {@see self::LOGO_SLOTS}), or null when images are off or no slot
     * holds a usable raster image.
     */
    public function logo(Brand $brand): ?BrandAsset
    {
        return $this->firstUsable($brand, self::LOGO_SLOTS);
    }

    /**
     * The absolute URL of an asset: an `https://` asset URL as is, a root-relative one on `$origin`. Null when the
     * result is not a plain absolute http(s) URL (an origin with a path, a query or credentials is refused too).
     */
    public static function absoluteUrl(BrandAsset $asset, string $origin): ?string
    {
        // BrandAsset refuses an unsafe URL in its constructor (root-relative or https://, no quotes, brackets or spaces).
        if (str_starts_with($asset->url, 'https://')) {
            return $asset->url;
        }
        if (!self::isOrigin($origin)) {
            return null;
        }

        $url = $origin . $asset->url;

        return preg_match(self::ABSOLUTE_URL_PATTERN, $url) === 1 ? $url : null;
    }

    /**
     * Whether a usable raster image: PNG, JPEG or WebP (never SVG or ICO, whatever its origin) with a safe URL.
     */
    public static function isUsableImage(BrandAsset $asset): bool
    {
        return in_array($asset->mimeType, BrandAsset::UPLOAD_MIME_TYPES, true) && BrandAsset::isSafeUrl($asset->url);
    }

    /**
     * @param list<string> $slots
     */
    private function firstUsable(Brand $brand, array $slots): ?BrandAsset
    {
        if (!$this->images) {
            return null;
        }
        foreach ($slots as $slot) {
            $asset = $brand->asset($slot);
            if ($asset instanceof BrandAsset && self::isUsableImage($asset)) {
                return $asset;
            }
        }

        return null;
    }

    private static function isOrigin(string $origin): bool
    {
        if (preg_match(self::ABSOLUTE_URL_PATTERN, $origin) !== 1) {
            return false;
        }
        // The pattern already requires an http or https scheme.
        $parts = parse_url($origin);
        if ($parts === false || !is_string($parts['host'] ?? null) || $parts['host'] === '') {
            return false;
        }

        return !isset($parts['path']) && !isset($parts['query']) && !isset($parts['fragment'])
            && !isset($parts['user']) && !isset($parts['pass']);
    }
}
