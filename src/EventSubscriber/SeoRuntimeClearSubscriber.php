<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\EventSubscriber;

use Nowo\SeoKitBundle\Service\PageHeadContext;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProvider;
use Nowo\SeoKitBundle\Service\SeoRuntime;
use Nowo\SeoKitBundle\Worker\UnitOfWorkFrames;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageSkipEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Contracts\Service\ResetInterface;

use const PHP_INT_MAX;

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
 *   not clear the outer unit's overrides, page head and private entity manager: the in-flight flag is set when a
 *   message is received and unset when it is handled, failed or the worker stops (audit MI-S-L2; same pattern as
 *   BeaconBundle 1.24.0).
 *
 * Which unit is outermost is tracked by {@see UnitOfWorkFrames} (1.24.1, audits NM / NL2-v), fed at priority
 * PHP_INT_MAX so another listener that throws or stops propagation cannot unbalance it: one frame per console run
 * (Command + input) that saves and restores the outer Messenger state, a frame left failing by a throwing console.error
 * listener healed at the next message, command or worker stop. The outermost command clears right after its frame is
 * pushed (PHP_INT_MAX) and at -4096 on terminate (verdict kept per event).
 *
 * The Messenger and console events are subscribed by name, so symfony/messenger and symfony/console stay optional.
 * The bundle wires the services through `$resettables` with IGNORE_ON_UNINITIALIZED_REFERENCE references, so a
 * boundary only resets what the unit of work built and never builds anything itself. The positional arguments are
 * kept for direct construction.
 */
final readonly class SeoRuntimeClearSubscriber implements EventSubscriberInterface, ResetInterface
{
    /** Messenger event names (class names), subscribed without requiring symfony/messenger. */
    public const MESSENGER_WORKER_MESSAGE_RECEIVED = WorkerMessageReceivedEvent::class;
    public const MESSENGER_WORKER_MESSAGE_HANDLED  = WorkerMessageHandledEvent::class;
    public const MESSENGER_WORKER_MESSAGE_FAILED   = WorkerMessageFailedEvent::class;
    public const MESSENGER_WORKER_STOPPED          = WorkerStoppedEvent::class;

    /** Messenger skip event (only dispatched by `messenger:failed:retry`): the message is no longer in flight. */
    public const MESSENGER_WORKER_MESSAGE_SKIPPED = WorkerMessageSkipEvent::class;

    /** Console event names (ConsoleEvents::COMMAND / ERROR / TERMINATE), subscribed without requiring symfony/console. */
    public const CONSOLE_COMMAND   = 'console.command';
    public const CONSOLE_ERROR     = 'console.error';
    public const CONSOLE_TERMINATE = 'console.terminate';

    /** Units of work running in this process (deliberately not tagged kernel.reset; the service is not autoconfigured). */
    private UnitOfWorkFrames $frames;

    /**
     * @param iterable<ResetInterface> $resettables
     */
    public function __construct(
        private ?SeoRuntime $runtime = null,
        private ?PageHeadContext $pageHeadContext = null,
        private ?SeoSiteConfigProvider $siteConfigProvider = null,
        private iterable $resettables = [],
    ) {
        $this->frames = new UnitOfWorkFrames();
    }

    /**
     * @return array<string, array{0: string, 1: int}|list<array{0: string, 1: int}>|string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST                   => ['onRequest', 4096],
            KernelEvents::TERMINATE                 => 'onTerminate',
            self::MESSENGER_WORKER_MESSAGE_RECEIVED => [['onMessageReceivedStarted', PHP_INT_MAX], ['onMessageReceived', 4096]],
            self::MESSENGER_WORKER_MESSAGE_HANDLED  => [['onMessageFinishedStarted', PHP_INT_MAX], ['onMessageFinished', -4096]],
            self::MESSENGER_WORKER_MESSAGE_FAILED   => [['onMessageFinishedStarted', PHP_INT_MAX], ['onMessageFinished', -4096]],
            self::MESSENGER_WORKER_MESSAGE_SKIPPED  => ['onMessageFinishedStarted', PHP_INT_MAX],
            self::MESSENGER_WORKER_STOPPED          => ['onWorkerStopped', PHP_INT_MAX],
            // Bookkeeping first (PHP_INT_MAX), the end-of-command clear late: a listener in between cannot skip it.
            self::CONSOLE_COMMAND   => ['onConsoleCommand', PHP_INT_MAX],
            self::CONSOLE_ERROR     => ['onConsoleError', PHP_INT_MAX],
            self::CONSOLE_TERMINATE => [['onConsoleTerminateStarted', PHP_INT_MAX], ['onConsoleTerminate', -4096]],
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
     * A Messenger message is received (bookkeeping).
     */
    public function onMessageReceivedStarted(): void
    {
        $this->frames->messageReceived();
    }

    /**
     * Start of a Messenger message: nothing of the previous message or the worker's idle time is kept.
     */
    public function onMessageReceived(): void
    {
        $this->clearState();
    }

    /**
     * A Messenger message is handled, failed or skipped (bookkeeping).
     */
    public function onMessageFinishedStarted(): void
    {
        $this->frames->messageFinished();
    }

    /**
     * End of a Messenger message (handled or failed).
     */
    public function onMessageFinished(): void
    {
        $this->clearState();
    }

    /**
     * The worker stopped (possibly after a skipped message): no message is in flight any more.
     */
    public function onWorkerStopped(): void
    {
        $this->frames->workerStopped();
    }

    /**
     * A console run starts: pushes its frame; clears only when it is the outermost unit (outside a Messenger message).
     */
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        if ($this->frames->commandStarted($event)) {
            $this->clearState();
        }
    }

    /**
     * A console run failed (bookkeeping: its frame is marked failing until its own terminate).
     */
    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $this->frames->commandFailed($event);
    }

    /**
     * A console run ends (bookkeeping: its frame is removed, the outermost verdict kept for the late clear).
     */
    public function onConsoleTerminateStarted(ConsoleTerminateEvent $event): void
    {
        $this->frames->commandTerminated($event);
    }

    /**
     * End of a command: clears only when the outermost command ends outside a Messenger message.
     */
    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        if ($this->frames->isOutermost($event)) {
            $this->clearState();
        }
    }

    /**
     * Forgets the unit-of-work bookkeeping (not the SEO state: see clearState()). Not tagged kernel.reset and not
     * relied on in worker mode.
     */
    public function reset(): void
    {
        $this->frames->reset();
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
