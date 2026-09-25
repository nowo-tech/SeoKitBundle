<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model;

use Nowo\SeoKitBundle\Model\StructuredData\StructuredDataNode;

/**
 * Levels 1–2 of head resolution (overrides + page-derived). Level 3 comes from defaults SPI.
 */
final readonly class PageHeadInput
{
    /**
     * @param array<string, string> $pathsByLocale
     * @param list<StructuredDataNode> $structuredData
     * @param list<array{name: string, path: string}> $breadcrumbs
     * @param 'SoftwareApplication'|'WebPage'|null $schemaType
     */
    public function __construct(
        public string $path,
        public string $locale,
        public array $pathsByLocale = [],
        public ?string $titleOverride = null,
        public ?string $descriptionOverride = null,
        public ?string $canonicalOverride = null,
        public ?string $robotsOverride = null,
        public ?string $templateTitle = null,
        public ?string $templateDescription = null,
        public ?string $ogType = null,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public ?string $ogImage = null,
        public array $structuredData = [],
        public bool $isHome = false,
        public array $breadcrumbs = [],
        public ?string $schemaType = null,
    ) {
    }
}
