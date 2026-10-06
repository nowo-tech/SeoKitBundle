<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Optional extra robots.txt groups (AI crawlers, etc.) appended when the site is indexable.
 *
 * Companion package {@code nowo-tech/generative-seo-kit-bundle} implements this tag for GEO crawler policy.
 *
 * @phpstan-type RobotsGroup array{user_agent: string, allow?: list<string>, disallow?: list<string>}
 */
#[AutoconfigureTag('nowo_seo_kit.geo_robots_groups_provider')]
interface GeoRobotsGroupsProviderInterface
{
    /**
     * @return list<RobotsGroup>
     */
    public function groups(): array;
}
