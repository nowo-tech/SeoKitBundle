<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Enum;

use function array_column;

/**
 * Sitemap `<changefreq>` values accepted by `sitemap_changefreq` (sitemaps.org protocol).
 */
enum SitemapChangeFreq: string
{
    case Always  = 'always';
    case Hourly  = 'hourly';
    case Daily   = 'daily';
    case Weekly  = 'weekly';
    case Monthly = 'monthly';
    case Yearly  = 'yearly';
    case Never   = 'never';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
