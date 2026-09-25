<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model;

use Nowo\SeoKitBundle\Service\PageHeadResolver;

/** Level 3 site-wide fall-backs for {@see PageHeadResolver}. */
final readonly class PageHeadDefaults
{
    public function __construct(
        public string $siteName,
        public string $title,
        public string $description,
        public string $robots,
        public ?string $ogImage = null,
        public ?int $ogImageWidth = 1200,
        public ?int $ogImageHeight = 630,
    ) {
    }
}
