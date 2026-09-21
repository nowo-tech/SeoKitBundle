<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model\StructuredData;

use Override;

/**
 * The site itself, tied to its publisher.
 */
final class WebSiteNode extends AbstractNode
{
    public function __construct(
        private readonly string $name,
        private readonly string $url,
        private readonly string $inLanguage,
        private readonly ?string $publisherId = null,
    ) {
    }

    #[Override]
    public function toArray(): array
    {
        return self::prune([
            '@type'      => 'WebSite',
            '@id'        => $this->url . '#website',
            'name'       => $this->name,
            'url'        => $this->url,
            'inLanguage' => $this->inLanguage,
            'publisher'  => $this->publisherId === null ? null : ['@id' => $this->publisherId],
        ]);
    }
}
