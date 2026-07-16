<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\EventSubscriber;

use Nowo\SeoKitBundle\Service\SeoRuntime;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Clears request-scoped SEO overrides after the response is sent.
 */
final readonly class SeoRuntimeClearSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SeoRuntime $runtime,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => 'onTerminate'];
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $this->runtime->clear();
    }
}
