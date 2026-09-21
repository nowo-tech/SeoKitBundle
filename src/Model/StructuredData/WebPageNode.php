<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model\StructuredData;

use Override;

/**
 * A page that is not a product: solutions, the catalogue, resources, about, contact and the legal
 * documents. The type tells a crawler this URL is a page of the site, not another organisation.
 */
final class WebPageNode extends AbstractNode
{
    public function __construct(
        private readonly string $name,
        private readonly string $url,
        private readonly string $description = '',
        private readonly string $inLanguage = '',
    ) {
    }

    #[Override]
    public function toArray(): array
    {
        return self::prune([
            '@type'       => 'WebPage',
            'name'        => $this->name,
            'url'         => $this->url,
            'description' => $this->description,
            'inLanguage'  => $this->inLanguage,
        ]);
    }
}
