<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Optional host extra robots.txt groups (AI crawlers, etc.) appended when the site is indexable.
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
