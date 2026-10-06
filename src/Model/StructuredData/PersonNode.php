<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model\StructuredData;

use Override;

/**
 * Person (optionally also Physician) in the site graph.
 */
final class PersonNode extends AbstractNode
{
    /**
     * @param list<string>|string $type
     */
    public function __construct(
        private readonly string $name,
        private readonly string $url,
        private readonly string $worksForId,
        private readonly string $jobTitle,
        private readonly string|array $type = 'Person',
        private readonly ?string $idSuffix = '#person',
        private readonly ?string $image = null,
        private readonly ?string $medicalSpecialty = null,
    ) {
    }

    #[Override]
    public function toArray(): array
    {
        return self::prune([
            '@type'            => $this->type,
            '@id'              => $this->url . ($this->idSuffix ?? '#person'),
            'name'             => $this->name,
            'url'              => $this->url,
            'jobTitle'         => $this->jobTitle,
            'image'            => $this->image,
            'worksFor'         => ['@id' => $this->worksForId],
            'medicalSpecialty' => $this->medicalSpecialty,
        ]);
    }
}
