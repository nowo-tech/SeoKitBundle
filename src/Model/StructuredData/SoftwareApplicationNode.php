<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model\StructuredData;

use Override;

/**
 * A product page: the three engines are software, and that is the type a result for them should
 * wear. Price is absent on purpose — inventing an offer would be a richer result built on a lie.
 */
final class SoftwareApplicationNode extends AbstractNode
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
            '@type'               => 'SoftwareApplication',
            'name'                => $this->name,
            'url'                 => $this->url,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem'     => 'Web',
            'description'         => $this->description,
            'inLanguage'          => $this->inLanguage,
        ]);
    }
}
