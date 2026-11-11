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
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Contracts\Service\ResetInterface;

use function max;

/**
 * Keeps the bundle's request-scoped state (runtime overrides, described page head, site settings memo) bound to one
 * unit of work, even when the services resetter does not run between units of work (a host that removes or bypasses
 * services_resetter, `messenger:consume --no-reset`, long-running console processes):
 *
 * - HTTP: at the start of every main request (before controllers run) and at kernel.terminate. Sub-requests
 *   (fragments, ESI) share the state of their main request;
 * - Messenger consumers: when a message is received and after it is handled or failed (e-mails rendered in a
 *   handler never see the previous message's overrides);
 * - console: when the OUTERMOST command starts and when it terminates, and only while no Messenger message is in
 *   flight. A command run in-process from another command or from a message handler (`Application::doRun()`) must
 *   not clear the outer unit's overrides and page head: the nesting depth is counted on console.command /
 *   console.terminate, and the in-flight flag is set when a message is received and unset when it is handled, failed
 *   or the worker stops (audit MI-S-L2; same pattern as BeaconBundle 1.24.0).
 *
 * The Messenger and console events are subscribed by name, so symfony/messenger and symfony/console stay optional.
 * The bundle wires the services through `$resettables` with IGNORE_ON_UNINITIALIZED_REFERENCE references, so a
 * boundary only resets what the unit of work built and never builds anything itself. The positional arguments are
 * kept for direct construction.
 */
final class SeoRuntimeClearSubscriber implements EventSubscriberInterface
{
    /** Messenger event names (class names), subscribed without requiring symfony/messenger. */
    public const MESSENGER_WORKER_MESSAGE_RECEIVED = WorkerMessageReceivedEvent::class;
    public const MESSENGER_WORKER_MESSAGE_HANDLED  = WorkerMessageHandledEvent::class;
    public const MESSENGER_WORKER_MESSAGE_FAILED   = WorkerMessageFailedEvent::class;
    public const MESSENGER_WORKER_STOPPED          = WorkerStoppedEvent::class;

    /** Console event names (ConsoleEvents::COMMAND / TERMINATE), subscribed without requiring symfony/console. */
    public const CONSOLE_COMMAND   = 'console.command';
    public const CONSOLE_TERMINATE = 'console.terminate';

    /**
     * Console commands currently running in this process (nested in-process runs count). Process-lifetime counter,
     * bounded by the nesting depth; intentionally not reset by kernel.reset.
     */
    private int $consoleDepth = 0;

    /** A Messenger message is being handled (between received and handled / failed / worker stopped). */
    private bool $messageInFlight = false;

    /**
     * @param iterable<ResetInterface> $resettables
     */
    public function __construct(
        private readonly ?SeoRuntime $runtime = null,
        private readonly ?PageHeadContext $pageHeadContext = null,
        private readonly ?SeoSiteConfigProvider $siteConfigProvider = null,
        private readonly iterable $resettables = [],
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST                   => ['onRequest', 4096],
            KernelEvents::TERMINATE                 => 'onTerminate',
            self::MESSENGER_WORKER_MESSAGE_RECEIVED => ['onMessageReceived', 4096],
            self::MESSENGER_WORKER_MESSAGE_HANDLED  => ['onMessageFinished', -4096],
            self::MESSENGER_WORKER_MESSAGE_FAILED   => ['onMessageFinished', -4096],
            self::MESSENGER_WORKER_STOPPED          => ['onWorkerStopped', -4096],
            self::CONSOLE_COMMAND                   => ['onConsoleCommand', 4096],
            self::CONSOLE_TERMINATE                 => ['onConsoleTerminate', -4096],
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
     * Start of a Messenger message: nothing of the previous message or the worker's idle time is kept.
     */
    public function onMessageReceived(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = true;
        $this->clearState();
    }

    /**
     * End of a Messenger message (handled or failed).
     */
    public function onMessageFinished(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = false;
        $this->clearState();
    }

    /**
     * The worker stopped (possibly after a skipped message): no message is in flight any more.
     */
    public function onWorkerStopped(): void
    {
        // @igor-ignore - Unit-of-work flag, set and unset at the Messenger boundaries.
        $this->messageInFlight = false;
    }

    /**
     * Start of a command: clears only when the outermost command starts outside a Messenger message.
     */
    public function onConsoleCommand(): void
    {
        // @igor-ignore - Nesting counter of the running commands (no request data).
        if (++$this->consoleDepth === 1 && !$this->messageInFlight) {
            $this->clearState();
        }
    }

    /**
     * End of a command: clears only when the outermost command ends outside a Messenger message.
     */
    public function onConsoleTerminate(): void
    {
        // @igor-ignore - Nesting counter of the running commands (no request data).
        $this->consoleDepth = max(0, $this->consoleDepth - 1);
        if ($this->consoleDepth === 0 && !$this->messageInFlight) {
            $this->clearState();
        }
    }

    /**
     * Clears the runtime overrides, the described page head and the site settings memo. Called at every unit-of-work
     * boundary (main request start, kernel.terminate, Messenger message received / handled / failed, outermost console
     * command start / terminate).
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
