<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Model\PageHeadDefaults;

use function is_array;
use function is_int;
use function is_string;

/**
 * YAML {@code nowo_seo_kit.defaults} mapped into PageHead when {@code base_url} is set.
 *
 * Hosts may alias {@see PageHeadDefaultsProviderInterface} to a custom service.
 */
final readonly class ConfigPageHeadDefaultsProvider implements PageHeadDefaultsProviderInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $config,
    ) {
    }

    public function forLocale(string $locale): PageHeadDefaults
    {
        unset($locale);

        $defaults = is_array($this->config['defaults'] ?? null) ? $this->config['defaults'] : [];
        $og       = is_array($defaults['open_graph'] ?? null) ? $defaults['open_graph'] : [];

        $siteName    = is_string($defaults['site_name'] ?? null) ? $defaults['site_name'] : '';
        $title       = is_string($defaults['title'] ?? null) && $defaults['title'] !== '' ? $defaults['title'] : $siteName;
        $description = is_string($defaults['description'] ?? null) ? $defaults['description'] : '';
        $robots      = is_string($defaults['robots'] ?? null) && $defaults['robots'] !== ''
            ? $defaults['robots']
            : 'index, follow';
        $ogImage = is_string($og['image'] ?? null) && $og['image'] !== '' ? $og['image'] : null;
        $width   = is_int($og['image_width'] ?? null) ? $og['image_width'] : 1200;
        $height  = is_int($og['image_height'] ?? null) ? $og['image_height'] : 630;

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
