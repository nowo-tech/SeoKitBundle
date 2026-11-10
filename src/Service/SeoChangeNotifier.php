<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use DateTimeImmutable;
use Nowo\SeoKitBundle\Event\SeoSiteSettingsChangedEvent;
use Nowo\SeoKitBundle\Event\SeoSurfaceChangedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Dispatches the id-only change events of the persisted SEO data after a write (since 1.22); without a dispatcher it
 * does nothing. Used by the admin, the JSON API and {@see SeoSurfaceManager}.
 */
final readonly class SeoChangeNotifier
{
    /**
     * @param EventDispatcherInterface|null $eventDispatcher Receives the events (null: nothing is dispatched)
     * @param TokenStorageInterface|null $tokenStorage Actor of the events (null without SecurityBundle)
     */
    public function __construct(
        private ?EventDispatcherInterface $eventDispatcher = null,
        private ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    /**
     * A surface override was saved (`$deleted` false) or cleared (true).
     */
    public function surfaceChanged(string $surfaceKey, string $locale, ?int $surfaceId, bool $deleted): void
    {
        $this->eventDispatcher?->dispatch(new SeoSurfaceChangedEvent($surfaceKey, $locale, $surfaceId, $deleted, $this->actorId(), new DateTimeImmutable()));
    }

    /**
     * The site settings were saved.
     */
    public function settingsChanged(): void
    {
        $this->eventDispatcher?->dispatch(new SeoSiteSettingsChangedEvent($this->actorId(), new DateTimeImmutable()));
    }

    private function actorId(): ?string
    {
        return $this->tokenStorage?->getToken()?->getUserIdentifier();
    }
}
