<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model;

/**
 * Resolved SEO metadata for the current request (or an arbitrary context).
 *
 * @phpstan-type OpenGraphArray array{enabled: bool, type: string, title: ?string, description: ?string, image: ?string, url: ?string, site_name: ?string, locale: ?string}
 * @phpstan-type TwitterArray array{enabled: bool, card: string, title: ?string, description: ?string, image: ?string, site: ?string, creator: ?string}
 * @phpstan-type JsonLdArray array{enabled: bool, graph: list<array<string, mixed>>}
 * @phpstan-type AlternateArray array{locale: string, url: string, hreflang: string}
 */
final class SeoMetadata
{
    /**
     * @param list<AlternateArray>    $alternates
     * @param OpenGraphArray          $openGraph
     * @param TwitterArray            $twitter
     * @param JsonLdArray             $jsonLd
     * @param array<string, mixed>    $extra
     */
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $robots,
        public readonly ?string $canonical,
        public readonly array $alternates,
        public readonly array $openGraph,
        public readonly array $twitter,
        public readonly array $jsonLd,
        public readonly ?string $keywords = null,
        public readonly ?string $author = null,
        public readonly array $extra = [],
        public readonly string $source = 'defaults',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'robots' => $this->robots,
            'canonical' => $this->canonical,
            'alternates' => $this->alternates,
            'open_graph' => $this->openGraph,
            'twitter' => $this->twitter,
            'json_ld' => $this->jsonLd,
            'keywords' => $this->keywords,
            'author' => $this->author,
            'extra' => $this->extra,
            'source' => $this->source,
        ];
    }
}
