<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Optional host signal for the site-wide indexability master switch.
 *
 * When any provider returns false, robots.txt disallows `/` and the sitemap is empty/404.
 */
#[AutoconfigureTag('nowo_seo_kit.indexability_provider')]
interface SiteIndexabilityProviderInterface
{
    public function isIndexable(): bool;
}
