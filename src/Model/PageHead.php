<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model;

use Nowo\SeoKitBundle\Model\StructuredData\StructuredDataGraph;

/** Resolved head metadata for one public URL. */
final readonly class PageHead
{
    /**
     * @param list<string> $ogLocaleAlternates
     * @param array<string, string> $pathsByLocale
     * @param list<array{name: string, path: string}> $breadcrumbs
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public string $robots,
        public HreflangSet $hreflang,
        public string $ogSiteName,
        public string $ogType,
        public string $ogTitle,
        public string $ogDescription,
        public ?string $ogImage,
        public ?int $ogImageWidth,
        public ?int $ogImageHeight,
        public string $ogLocale,
        public array $ogLocaleAlternates,
        public string $twitterCard,
        public StructuredDataGraph $structuredData,
        public array $pathsByLocale = [],
        public array $breadcrumbs = [],
    ) {
    }

    public function isIndexable(): bool
    {
        return !str_contains(strtolower($this->robots), 'noindex');
    }
}
