<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model\StructuredData;

use Override;

/**
 * The publisher of the site. Emitted on every page so the knowledge panel has one stable subject.
 */
final class OrganizationNode extends AbstractNode
{
    /**
     * @param list<string> $sameAs social and directory profiles that confirm the identity
     * @param array{street: ?string, postalCode: ?string, locality: ?string, region: ?string, country: ?string}|null $address postal address, when the organisation publishes one
     */
    public function __construct(
        private readonly string $name,
        private readonly string $url,
        private readonly ?string $logo = null,
        private readonly ?string $description = null,
        private readonly array $sameAs = [],
        private readonly ?string $contactEmail = null,
        private readonly ?string $legalName = null,
        private readonly ?string $telephone = null,
        private readonly ?array $address = null,
    ) {
    }

    #[Override]
    public function toArray(): array
    {
        return self::prune([
            '@type'       => 'Organization',
            '@id'         => $this->url . '#organization',
            'name'        => $this->name,
            'legalName'   => $this->legalName,
            'url'         => $this->url,
            'logo'        => $this->logo,
            'description' => $this->description,
            'telephone'   => $this->telephone,
            'address'     => $this->address === null ? null : self::prune([
                '@type'           => 'PostalAddress',
                'streetAddress'   => $this->address['street'],
                'postalCode'      => $this->address['postalCode'],
                'addressLocality' => $this->address['locality'],
                'addressRegion'   => $this->address['region'],
                'addressCountry'  => $this->address['country'],
            ]),
            'sameAs'       => $this->sameAs,
            'contactPoint' => $this->contactEmail === null && $this->telephone === null ? null : self::prune([
                '@type'       => 'ContactPoint',
                'contactType' => 'sales',
                'email'       => $this->contactEmail,
                'telephone'   => $this->telephone,
            ]),
        ]);
    }
}
