<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Model\SitemapContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contributes absolute sitemap URLs from the host (CMS pages, blog articles, …). Since 1.24.0; replaces the
 * request-typed {@see SitemapUrlProviderInterface} (REQ-ARCH-001 rule 3).
 *
 * Autoconfigured with the same tag (`nowo_seo_kit.sitemap_url_provider`). A class that implements both interfaces is
 * called through this one only.
 *
 * @phpstan-type SitemapAlternate array{hreflang: string, href: string}
 * @phpstan-type SitemapEntry array{
 *     loc: string,
 *     changefreq?: string,
 *     priority?: string,
 *     lastmod?: string,
 *     alternates?: list<SitemapAlternate>
 * }
 */
#[AutoconfigureTag('nowo_seo_kit.sitemap_url_provider')]
interface SitemapEntryProviderInterface
{
    /**
     * @return list<SitemapEntry>
     */
    public function getEntriesFor(SitemapContext $context): array;
}
