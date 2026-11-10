<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Event;

use DateTimeImmutable;
use Nowo\SeoKitBundle\Service\SeoSurfaceKeys;
use Nowo\SeoKitBundle\Service\SeoSurfaceManagerInterface;

/**
 * A surface override (per-page SEO of one surface key and locale) was saved or cleared (since 1.22), from the admin,
 * the JSON API or {@see SeoSurfaceManagerInterface::saveOrClear()}. Dispatched after every successful save or clear
 * (also when the values did not change); never for an empty override that had no row, and not for the empty row
 * `getOrCreate()` writes. Scalars only: a host invalidates its HTTP
 * or CDN cache, or updates a projection, from it.
 */
final readonly class SeoSurfaceChangedEvent
{
    /**
     * @param string $surfaceKey Surface key (see {@see SeoSurfaceKeys})
     * @param string $locale Locale of the override
     * @param int|null $surfaceId Row id (the removed row's id when `deleted`)
     * @param bool $deleted True when the override was cleared (row removed: the surface inherits again)
     * @param string|null $actorId User identifier of the current user (null without a security token)
     * @param DateTimeImmutable $occurredAt When the write was flushed
     */
    public function __construct(
        public string $surfaceKey,
        public string $locale,
        public ?int $surfaceId,
        public bool $deleted,
        public ?string $actorId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
