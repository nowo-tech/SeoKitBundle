<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service\Persistence;

use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Model\SeoSiteConfig;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

/**
 * Reads editable SEO settings once per request (and optionally from cache.app).
 *
 * If the table is missing — during install, before migrations — falls back to environment defaults
 * instead of failing the whole page head.
 */
final class SeoSiteConfigProvider implements SeoSiteConfigProviderInterface, ResetInterface
{
    private const CACHE_KEY = 'nowo_seo_kit.site_config';

    private ?SeoSiteConfig $config = null;

    public function __construct(
        private readonly SeoSiteSettingsRepository $repository,
        private readonly ?CacheItemPoolInterface $cache = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly string $fallbackRobots = 'index, follow',
        private readonly string $fallbackContactEmail = '',
        private readonly string $fallbackSiteName = '',
    ) {
    }

    #[Override]
    public function get(): SeoSiteConfig
    {
        if ($this->config instanceof SeoSiteConfig) {
            return $this->config;
        }

        try {
            if ($this->cache instanceof CacheItemPoolInterface) {
                $item = $this->cache->getItem(self::CACHE_KEY);
                if ($item->isHit()) {
                    $cached = $item->get();
                    $config = $cached instanceof SeoSiteConfig ? $cached : $this->snapshot($this->repository->getOrCreate());
                } else {
                    $config = $this->snapshot($this->repository->getOrCreate());
                    $item->set($config);
                    $this->cache->save($item);
                }
            } else {
                $config = $this->snapshot($this->repository->getOrCreate());
            }
        } catch (Throwable $e) {
            $this->logger?->warning('SEO site settings could not be read; falling back to deployment defaults.', [
                'exception' => $e->getMessage(),
            ]);

            $config = $this->fallback();
        }

        return $this->config = $config;
    }

    #[Override]
    public function refresh(): void
    {
        $this->config = null;
        $this->cache?->deleteItem(self::CACHE_KEY);
    }

    #[Override]
    public function reset(): void
    {
        $this->config = null;
    }

    private function snapshot(SeoSiteSettings $settings): SeoSiteConfig
    {
        $byLocale = [];
        foreach ($settings->getTranslations() as $translation) {
            $byLocale[$translation->getLocale()] = [
                'title'                   => $translation->getDefaultTitle(),
                'description'             => $translation->getDefaultDescription(),
                'homeTitle'               => $translation->getHomeTitle(),
                'organisationDescription' => $translation->getOrganisationDescription(),
            ];
        }

        return new SeoSiteConfig(
            siteName: $settings->getSiteName(),
            titleTemplate: $settings->getTitleTemplate(),
            indexable: $settings->isIndexable(),
            robots: $settings->effectiveRobots(),
            openGraphImage: $settings->getDefaultOpenGraphImage(),
            twitterSite: $settings->getTwitterSite(),
            organisationLegalName: $settings->getOrganisationLegalName(),
            organisationLogo: $settings->getOrganisationLogo(),
            contactEmail: $settings->getContactEmail(),
            contactPhone: $settings->getContactPhone(),
            streetAddress: $settings->getStreetAddress(),
            postalCode: $settings->getPostalCode(),
            addressLocality: $settings->getAddressLocality(),
            addressRegion: $settings->getAddressRegion(),
            addressCountry: $settings->getAddressCountry(),
            socialProfiles: $settings->getSocialProfiles(),
            googleSiteVerification: $settings->getGoogleSiteVerification(),
            bingSiteVerification: $settings->getBingSiteVerification(),
            byLocale: $byLocale,
        );
    }

    private function fallback(): SeoSiteConfig
    {
        return new SeoSiteConfig(
            siteName: $this->fallbackSiteName,
            titleTemplate: '%page% · %site%',
            indexable: !str_contains(strtolower($this->fallbackRobots), 'noindex'),
            robots: $this->fallbackRobots,
            openGraphImage: null,
            twitterSite: null,
            organisationLegalName: null,
            organisationLogo: null,
            contactEmail: $this->fallbackContactEmail === '' ? null : $this->fallbackContactEmail,
            contactPhone: null,
            streetAddress: null,
            postalCode: null,
            addressLocality: null,
            addressRegion: null,
            addressCountry: null,
            socialProfiles: [],
            googleSiteVerification: null,
            bingSiteVerification: null,
            byLocale: [],
        );
    }
}
