<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\EventSubscriber;

use Nowo\SeoKitBundle\Service\PageHeadContext;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProvider;
use Nowo\SeoKitBundle\Service\SeoRuntime;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps the bundle's request-scoped state bound to one request.
 *
 * At the start of every main request (before controllers run) it clears the runtime overrides, the described
 * page head and the site settings memo, so nothing leaks from the previous request even when the kernel is not
 * reset between requests (FrankenPHP worker mode without services_resetter). Sub-requests (fragments, ESI)
 * share the state of their main request. The runtime overrides are also cleared after the response is sent.
 */
final readonly class SeoRuntimeClearSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SeoRuntime $runtime,
        private ?PageHeadContext $pageHeadContext = null,
        private ?SeoSiteConfigProvider $siteConfigProvider = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST   => ['onRequest', 4096],
            KernelEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->runtime->clear();
        $this->pageHeadContext?->reset();
        $this->siteConfigProvider?->reset();
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $this->runtime->clear();
    }
}
