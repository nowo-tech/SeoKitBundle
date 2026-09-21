<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service\StructuredData;

use Nowo\SeoKitBundle\Model\SeoSiteConfig;
use Nowo\SeoKitBundle\Model\StructuredData\OrganizationNode;
use Nowo\SeoKitBundle\Model\StructuredData\StructuredDataGraph;
use Nowo\SeoKitBundle\Model\StructuredData\WebSiteNode;

/**
 * Builds the shared Organization + WebSite graph from a {@see SeoSiteConfig} snapshot.
 */
final class SiteStructuredDataFactory
{
    /**
     * @param callable(string): string|null $translate Optional translator for missing site name / description
     */
    public function build(
        SeoSiteConfig $config,
        string $locale,
        string $origin,
        ?string $defaultLogoAbsolute = null,
        ?callable $translate = null,
    ): StructuredDataGraph {
        $name = $config->siteName !== ''
            ? $config->siteName
            : (string) ($translate !== null ? $translate('site.seo.site_name') : '');

        $description = $config->organisationDescriptionFor($locale)
            ?? $config->descriptionFor($locale)
            ?? (string) ($translate !== null ? $translate('site.seo.default_description') : '');

        $organisation = new OrganizationNode(
            name: $name,
            url: $origin,
            logo: $this->absolute($config->organisationLogo, $origin) ?? $defaultLogoAbsolute,
            description: $description !== '' ? $description : null,
            sameAs: $config->socialProfiles,
            contactEmail: $config->contactEmail,
            legalName: $config->organisationLegalName,
            telephone: $config->contactPhone,
            address: $config->hasPostalAddress() ? [
                'street'     => $config->streetAddress,
                'postalCode' => $config->postalCode,
                'locality'   => $config->addressLocality,
                'region'     => $config->addressRegion,
                'country'    => $config->addressCountry,
            ] : null,
        );

        return new StructuredDataGraph(
            $organisation,
            new WebSiteNode(
                name: $name,
                url: $origin,
                inLanguage: $locale,
                publisherId: $organisation->toArray()['@id'],
            ),
        );
    }

    private function absolute(?string $path, string $origin): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim($origin, '/') . '/' . ltrim($path, '/');
    }
}
