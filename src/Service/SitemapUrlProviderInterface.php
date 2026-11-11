<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Model\SitemapContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Contributes absolute sitemap URLs from the host (CMS pages, blog articles, …).
 *
 * @deprecated since 1.24.0, implement {@see SitemapEntryProviderInterface} (framework-free
 *             {@see SitemapContext} instead of the request, REQ-ARCH-001 rule 3); removed
 *             in 2.0
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
interface SitemapUrlProviderInterface
{
    /**
     * @return list<SitemapEntry>
     */
    public function getEntries(Request $request): array;
}
