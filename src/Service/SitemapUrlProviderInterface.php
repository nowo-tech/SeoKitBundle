<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Contributes absolute sitemap URLs from the host (CMS pages, blog articles, …).
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
