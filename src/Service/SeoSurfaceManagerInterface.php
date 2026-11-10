<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Event\SeoSurfaceChangedEvent;

/**
 * Load / save / clear surface overrides (since 1.22 as an interface; {@see SeoSurfaceManager} is the implementation,
 * registered with `persistence.enabled`). Type-hint this interface in host code instead of the final class.
 */
interface SeoSurfaceManagerInterface
{
    /**
     * The stored override of this surface and locale; null when the surface inherits.
     */
    public function find(string $surfaceKey, string $locale): ?SeoSurface;

    /**
     * The stored override, or a new **empty row persisted and flushed** for this surface and locale (unchanged since
     * 1.8). Creating that row dispatches no event: only {@see self::saveOrClear()} does. Use {@see self::find()} plus a
     * new `SeoSurface` when an empty row must not be written.
     */
    public function getOrCreate(string $surfaceKey, string $locale): SeoSurface;

    /**
     * Persists when any field is set; removes the row when every override is empty. Dispatches
     * {@see SeoSurfaceChangedEvent} after the write (since 1.22).
     *
     * @return bool true when a row remains, false when cleared / never stored
     */
    public function saveOrClear(SeoSurface $surface): bool;

    /**
     * Whether every override field of the surface is empty.
     */
    public function isEmpty(SeoSurface $seo): bool;
}
