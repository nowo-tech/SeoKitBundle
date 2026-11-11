<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Model\SitemapContext;

/**
 * Builds absolute/relative SEO paths and locale alternates without HTTP types (since 1.24.0; replaces
 * {@see SeoPathBuilderInterface}, REQ-ARCH-001 rule 3). Implemented by {@see SeoPathBuilder}.
 */
interface SeoUrlBuilderInterface
{
    /**
     * Absolute URL for a path: an `http(s)://` path is returned as is; otherwise `nowo_seo_kit.base_url` wins, then
     * the context origin.
     */
    public function absoluteUrlFor(SitemapContext $context, string $path): string;

    /**
     * Public path of a configured static page route in a locale, or `$fallbackPath`.
     */
    public function pagePath(string $route, string $locale, ?string $fallbackPath = null): ?string;

    /**
     * Map a request slug (canonical or translated) to the configured slug key.
     */
    public function resolveCanonicalSlug(string $route, string $slug): string;

    /**
     * Path of a slug route in a locale, or null when the route has no pattern nor a specific path.
     */
    public function slugPath(string $route, string $locale, string $slug): ?string;
}
