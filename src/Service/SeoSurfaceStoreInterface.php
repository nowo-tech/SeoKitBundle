<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Event\SeoSurfaceChangedEvent;
use Nowo\SeoKitBundle\Model\SeoSurfaceData;

/**
 * Surface overrides as plain data (since 1.24.0, REQ-ARCH-001 rules 1 and 3): no Doctrine entity crosses this port.
 * Implemented by {@see SeoSurfaceManager} and registered with `persistence.enabled`. Replaces the entity-typed
 * {@see SeoSurfaceManagerInterface}.
 *
 * Writes go to the entity manager that maps the SeoKit entities (`nowo_seo_kit.doctrine.entity_manager`, or the
 * default manager when it is null). Each write dispatches {@see SeoSurfaceChangedEvent} after the flush.
 */
interface SeoSurfaceStoreInterface
{
    /**
     * The stored override of this surface and locale; null when the surface inherits.
     */
    public function findData(string $surfaceKey, string $locale): ?SeoSurfaceData;

    /**
     * Stores the override, or removes the stored row when every field is empty. Never writes an empty row.
     *
     * @return bool true when a row remains, false when cleared / never stored
     */
    public function save(SeoSurfaceData $data): bool;

    /**
     * Removes the override of this surface and locale.
     *
     * @return bool true when a row was removed, false when none was stored
     */
    public function clear(string $surfaceKey, string $locale): bool;
}
