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
final readonly class Seo
{
    /**
     * @param array<string, string>|null $openGraph Extra Open Graph fields (type, image, ...)
     * @param array<string, string>|null $twitter Extra Twitter card fields
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $robots = null,
        public ?string $canonical = null,
        public ?string $keywords = null,
        public ?string $author = null,
        public ?array $openGraph = null,
        public ?array $twitter = null,
        public bool $noindex = false,
    ) {
    }
}
