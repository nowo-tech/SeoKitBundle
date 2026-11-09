<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\Brand\Contract\Brand;
use Nowo\Brand\Contract\BrandAsset;
use Nowo\SeoKitBundle\Brand\SeoKitBrand;
use Nowo\SeoKitBundle\Model\PageHeadDefaults;

use function is_array;
use function is_int;
use function is_string;
use function trim;

/**
 * YAML {@code nowo_seo_kit.defaults} mapped into PageHead when {@code base_url} is set.
 *
 * Hosts may alias {@see PageHeadDefaultsProviderInterface} to a custom service.
 *
 * Spec 220 S6: with a brand ({@see SeoKitBrand}), an empty `site_name` becomes the brand name (and so the default
 * title and `og:site_name`), and with `brand.images: true` an empty `open_graph.image` becomes the brand logo on the
 * `base_url` origin, with the logo's own size. Configured values always win.
 */
final readonly class ConfigPageHeadDefaultsProvider implements PageHeadDefaultsProviderInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $config,
        private ?SeoKitBrand $brand = null,
        private ?AbsoluteUrlBuilder $urls = null,
    ) {
    }

    public function forLocale(string $locale): PageHeadDefaults
    {
        unset($locale);

        $defaults = is_array($this->config['defaults'] ?? null) ? $this->config['defaults'] : [];
        $og       = is_array($defaults['open_graph'] ?? null) ? $defaults['open_graph'] : [];

        $siteName = is_string($defaults['site_name'] ?? null) ? $defaults['site_name'] : '';
        $brand    = $this->brand?->current();
        if ($brand instanceof Brand && trim($siteName) === '') {
            $siteName = $brand->name;
        }
        $title       = is_string($defaults['title'] ?? null) && $defaults['title'] !== '' ? $defaults['title'] : $siteName;
        $description = is_string($defaults['description'] ?? null) ? $defaults['description'] : '';
        $robots      = is_string($defaults['robots'] ?? null) && $defaults['robots'] !== ''
            ? $defaults['robots']
            : 'index, follow';
        $ogImage = is_string($og['image'] ?? null) && $og['image'] !== '' ? $og['image'] : null;
        $width   = is_int($og['image_width'] ?? null) ? $og['image_width'] : 1200;
        $height  = is_int($og['image_height'] ?? null) ? $og['image_height'] : 630;

        $brandImage = $ogImage === null && $brand instanceof Brand ? $this->brand->ogImage($brand) : null;
        $brandUrl   = $brandImage instanceof BrandAsset && $this->urls instanceof AbsoluteUrlBuilder
            ? SeoKitBrand::absoluteUrl($brandImage, $this->urls->origin())
            : null;
        if ($brandImage instanceof BrandAsset && $brandUrl !== null) {
            $ogImage = $brandUrl;
            $width   = $brandImage->width;
            $height  = $brandImage->height;
        }

        return new PageHeadDefaults(
            siteName: $siteName,
            title: $title,
            description: $description,
            robots: $robots,
            ogImage: $ogImage,
            ogImageWidth: $ogImage === null ? null : $width,
            ogImageHeight: $ogImage === null ? null : $height,
        );
    }
}
