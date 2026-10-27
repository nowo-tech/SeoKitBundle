<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\EventSubscriber;

use Nowo\SeoKitBundle\Service\SeoMetadataResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * `x_robots_tag.enabled`: sends the page's resolved robots value (defaults → slug routes → pages → slugs → attribute
 * → runtime, `indexable: false` forcing noindex) as an `X-Robots-Tag` header on every main response — HTML, JSON or a
 * file alike, with or without `nowo_seo_head()` in the layout. A response that already carries the header keeps it.
 */
final readonly class XRobotsTagSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SeoMetadataResolver $resolver,
        private bool $enabled = false,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onResponse', -16],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $headers = $event->getResponse()->headers;
        if (!$this->enabled || !$event->isMainRequest() || $headers->has('X-Robots-Tag')) {
            return;
        }

        $headers->set('X-Robots-Tag', $this->resolver->resolve($event->getRequest())->robots);
    }
}
