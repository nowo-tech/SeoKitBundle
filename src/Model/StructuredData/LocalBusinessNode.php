<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model\StructuredData;

use Override;

/**
 * Local business / professional practice JSON-LD. Hosts pass schema.org {@code @type} values
 * that match the real organisation (do not invent medical types).
 */
final class LocalBusinessNode extends AbstractNode
{
    /**
     * @param list<string>|string $type
     * @param list<string> $sameAs
     * @param list<string> $openingHours
     * @param array{street: string, postalCode: string, locality: string, region: string, country: string} $address
     */
    public function __construct(
        private readonly string $name,
        private readonly string $url,
        private readonly string $telephone,
        private readonly array $address,
        private readonly string|array $type = 'LocalBusiness',
        private readonly ?string $idSuffix = '#localbusiness',
        private readonly ?string $email = null,
        private readonly ?string $logo = null,
        private readonly ?string $description = null,
        private readonly array $sameAs = [],
        private readonly array $openingHours = [],
        private readonly ?string $priceRange = null,
        private readonly ?string $hasMap = null,
        private readonly ?string $employeeId = null,
        private readonly ?string $medicalSpecialty = null,
    ) {
    }

    #[Override]
    public function toArray(): array
    {
        return self::prune([
            '@type'            => $this->type,
            '@id'              => $this->url . ($this->idSuffix ?? '#localbusiness'),
            'name'             => $this->name,
            'url'              => $this->url,
            'telephone'        => $this->telephone,
            'email'            => $this->email,
            'logo'             => $this->logo,
            'image'            => $this->logo,
            'description'      => $this->description,
            'medicalSpecialty' => $this->medicalSpecialty,
            'priceRange'       => $this->priceRange,
            'address'          => self::prune([
                '@type'           => 'PostalAddress',
                'streetAddress'   => $this->address['street'],
                'postalCode'      => $this->address['postalCode'],
                'addressLocality' => $this->address['locality'],
                'addressRegion'   => $this->address['region'],
                'addressCountry'  => $this->address['country'],
            ]),
            'openingHours' => $this->openingHours,
            'sameAs'       => $this->sameAs,
            'hasMap'       => $this->hasMap,
            'employee'     => $this->employeeId !== null ? ['@id' => $this->employeeId] : null,
            'areaServed'   => [
                '@type' => 'City',
                'name'  => $this->address['locality'],
            ],
        ]);
    }
}
