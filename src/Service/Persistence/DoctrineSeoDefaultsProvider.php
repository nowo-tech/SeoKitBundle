<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service\Persistence;

use Nowo\SeoKitBundle\Service\SeoDefaultsProviderInterface;
use Nowo\SeoKitBundle\Service\SiteIndexabilityProviderInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

use function in_array;

/**
 * Maps Doctrine-backed {@see SeoSiteConfig} into SeoKit YAML-compatible defaults + indexability.
 */
#[AutoconfigureTag('nowo_seo_kit.defaults_provider')]
#[AutoconfigureTag('nowo_seo_kit.indexability_provider')]
final readonly class DoctrineSeoDefaultsProvider implements SeoDefaultsProviderInterface, SiteIndexabilityProviderInterface
{
    public function __construct(
        private SeoSiteConfigProviderInterface $configProvider,
    ) {
    }

    #[Override]
    public function getDefaults(): array
    {
        $config   = $this->configProvider->get();
        $defaults = [
            'site_name'      => $config->siteName,
            'title_template' => $this->toKitTitleTemplate($config->titleTemplate),
            'robots'         => $config->indexable ? $config->robots : 'noindex, nofollow',
            'verification'   => [
                'google' => $config->googleSiteVerification,
                'bing'   => $config->bingSiteVerification,
            ],
            'open_graph' => [
                'site_name' => $config->siteName,
                'image'     => $config->openGraphImage,
            ],
            'twitter' => [
                'site' => $config->twitterSite,
            ],
        ];

        $org = array_filter([
            'name'      => $config->organisationLegalName ?: $config->siteName,
            'logo'      => $config->organisationLogo,
            'email'     => $config->contactEmail,
            'telephone' => $config->contactPhone,
            'sameAs'    => $config->socialProfiles !== [] ? $config->socialProfiles : null,
        ], static fn (mixed $v): bool => !in_array($v, [null, '', []], true));

        if ($config->hasPostalAddress()) {
            $org['address'] = array_filter([
                '@type'           => 'PostalAddress',
                'streetAddress'   => $config->streetAddress,
                'postalCode'      => $config->postalCode,
                'addressLocality' => $config->addressLocality,
                'addressRegion'   => $config->addressRegion,
                'addressCountry'  => $config->addressCountry,
            ], static fn (mixed $v): bool => $v !== null && $v !== '');
        }

        if ($org !== []) {
            $defaults['json_ld'] = [
                'enabled'      => true,
                'organization' => $org,
            ];
        }

        return $defaults;
    }

    #[Override]
    public function isIndexable(): bool
    {
        return $this->configProvider->get()->indexable;
    }

    /**
     * Host editors use `%page% · %site%`; SeoKit YAML uses `{title}{separator}{site_name}`.
     */
    private function toKitTitleTemplate(string $template): string
    {
        if (str_contains($template, '{title}')) {
            return $template;
        }

        return str_replace(
            ['%page%', '%site%'],
            ['{title}', '{site_name}'],
            $template,
        );
    }
}
