<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Attribute;

use Attribute;

/**
 * Declares SEO metadata on a controller class or action.
 *
 * Values override YAML `pages` / `slug_routes` for the matched route.
 * Template placeholders `{param}` are replaced from request attributes.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Seo
{
    /**
     * @param array<string, string>|null $openGraph Extra Open Graph fields (type, image, ...)
     * @param array<string, string>|null $twitter   Extra Twitter card fields
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $robots = null,
        public readonly ?string $canonical = null,
        public readonly ?string $keywords = null,
        public readonly ?string $author = null,
        public readonly ?array $openGraph = null,
        public readonly ?array $twitter = null,
        public readonly bool $noindex = false,
    ) {
    }
}
