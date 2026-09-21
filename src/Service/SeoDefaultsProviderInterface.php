<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Optional host-provided defaults merged into YAML `defaults` before resolution.
 *
 * Later providers win on key conflicts (array_replace for scalars; nested open_graph / twitter /
 * json_ld / verification are shallow-merged).
 *
 * @phpstan-type DefaultsArray array<string, mixed>
 */
#[AutoconfigureTag('nowo_seo_kit.defaults_provider')]
interface SeoDefaultsProviderInterface
{
    /**
     * @return DefaultsArray Partial defaults layer (site_name, robots, open_graph, verification, …)
     */
    public function getDefaults(): array;
}
