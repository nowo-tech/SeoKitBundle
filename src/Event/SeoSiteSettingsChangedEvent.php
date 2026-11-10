<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Event;

use DateTimeImmutable;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProviderInterface;

/**
 * The site-wide SEO settings were saved from the admin (since 1.22). Dispatched after the flush and after
 * {@see SeoSiteConfigProviderInterface::refresh()}, so a listener reading the
 * provider sees the new values. Scalars only.
 */
final readonly class SeoSiteSettingsChangedEvent
{
    /**
     * @param string|null $actorId User identifier of the current user (null without a security token)
     * @param DateTimeImmutable $occurredAt When the write was flushed
     */
    public function __construct(
        public ?string $actorId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
