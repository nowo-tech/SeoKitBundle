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
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Keeps the bundle's request-scoped state (runtime overrides, described page head, site settings memo) bound to one
 * unit of work, even when the services resetter does not run between units of work (a host that removes or bypasses
 * services_resetter, `messenger:consume --no-reset`, long-running console processes):
 *
 * - HTTP: at the start of every main request (before controllers run) and at kernel.terminate. Sub-requests
 *   (fragments, ESI) share the state of their main request;
 * - Messenger consumers: when a message is received and after it is handled or failed (e-mails rendered in a
 *   handler never see the previous message's overrides);
 * - console: when a command starts and when it terminates.
 *
 * The Messenger and console events are subscribed by name, so symfony/messenger and symfony/console stay optional.
 * The bundle wires the services through `$resettables` with IGNORE_ON_UNINITIALIZED_REFERENCE references, so a
 * boundary only resets what the unit of work built and never builds anything itself. The positional arguments are
 * kept for direct construction.
 */
final readonly class SeoRuntimeClearSubscriber implements EventSubscriberInterface
{
    /** Messenger event names (class names), subscribed without requiring symfony/messenger. */
    public const MESSENGER_WORKER_MESSAGE_RECEIVED = WorkerMessageReceivedEvent::class;
    public const MESSENGER_WORKER_MESSAGE_HANDLED  = WorkerMessageHandledEvent::class;
    public const MESSENGER_WORKER_MESSAGE_FAILED   = WorkerMessageFailedEvent::class;

    /** Console event names (ConsoleEvents::COMMAND / TERMINATE), subscribed without requiring symfony/console. */
    public const CONSOLE_COMMAND   = 'console.command';
    public const CONSOLE_TERMINATE = 'console.terminate';

    /**
     * @param iterable<ResetInterface> $resettables
     */
    public function __construct(
        private ?SeoRuntime $runtime = null,
        private ?PageHeadContext $pageHeadContext = null,
        private ?SeoSiteConfigProvider $siteConfigProvider = null,
        private iterable $resettables = [],
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST                   => ['onRequest', 4096],
            KernelEvents::TERMINATE                 => 'onTerminate',
            self::MESSENGER_WORKER_MESSAGE_RECEIVED => ['clearState', 4096],
            self::MESSENGER_WORKER_MESSAGE_HANDLED  => ['clearState', -4096],
            self::MESSENGER_WORKER_MESSAGE_FAILED   => ['clearState', -4096],
            self::CONSOLE_COMMAND                   => ['clearState', 4096],
            self::CONSOLE_TERMINATE                 => ['clearState', -4096],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->clearState();
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $this->clearState();
    }

    /**
     * Clears the runtime overrides, the described page head and the site settings memo. Called at every unit-of-work
     * boundary (main request start, kernel.terminate, Messenger message received / handled / failed, console command
     * start / terminate).
     */
    public function clearState(): void
    {
        // @igor-ignore - Event subscriber clears request/runtime bridges; not cross-request leak.
        $this->runtime?->clear();
        $this->pageHeadContext?->reset();
        $this->siteConfigProvider?->reset();
        foreach ($this->resettables as $service) {
            $service->reset();
        }
    }
}
