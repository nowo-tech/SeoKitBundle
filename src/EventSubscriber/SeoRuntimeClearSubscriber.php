<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\EventSubscriber;

use Nowo\SeoKitBundle\Service\PageHeadContext;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProvider;
use Nowo\SeoKitBundle\Service\SeoRuntime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Contracts\Service\ResetInterface;

use function array_slice;
use function count;

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
 * Console bookkeeping (1.24.1, audit NM): the running commands are a stack pushed on console.command and popped on
 * console.terminate, both at PHP_INT_MAX, so another listener that throws or stops propagation cannot skip it. A
 * terminate pops its own command and every command started after it (a nested run whose terminate was skipped); a
 * terminate of a command that is not on the stack changes nothing (never a blind decrement). The outermost command
 * clears at its start (PHP_INT_MAX, right after the push) and at its end (-4096, decided by the early terminate).
 *
 * The Messenger and console events are subscribed by name, so symfony/messenger and symfony/console stay optional.
 * The bundle wires the services through `$resettables` with IGNORE_ON_UNINITIALIZED_REFERENCE references, so a
 * boundary only resets what the unit of work built and never builds anything itself. The positional arguments are
 * kept for direct construction.
 */
final class SeoRuntimeClearSubscriber implements EventSubscriberInterface, ResetInterface
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
     * Console commands currently running in this process, outermost first (nested in-process runs count). Bounded by
     * the nesting depth; deliberately not tagged kernel.reset (the service is not autoconfigured).
     *
     * @var list<Command>
     */
    private array $runningCommands = [];

    /** The outermost command whose console.terminate is under way: cleared at the late priority. */
    private ?Command $terminatingOutermost = null;

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

    /**
     * @return array<string, array{0: string, 1: int}|list<array{0: string, 1: int}>|string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST                   => ['onRequest', 4096],
            KernelEvents::TERMINATE                 => 'onTerminate',
            self::MESSENGER_WORKER_MESSAGE_RECEIVED => ['onMessageReceived', 4096],
            self::MESSENGER_WORKER_MESSAGE_HANDLED  => ['onMessageFinished', -4096],
            self::MESSENGER_WORKER_MESSAGE_FAILED   => ['onMessageFinished', -4096],
            self::MESSENGER_WORKER_STOPPED          => ['onWorkerStopped', -4096],
            // Bookkeeping first (PHP_INT_MAX), the end-of-command clear late: a listener in between cannot skip it.
            self::CONSOLE_COMMAND   => ['onConsoleCommand', PHP_INT_MAX],
            self::CONSOLE_TERMINATE => [['onConsoleTerminateStart', PHP_INT_MAX], ['onConsoleTerminate', -4096]],
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
     * Start of a command: pushed on the running-command stack; clears only when the outermost command starts outside a
     * Messenger message.
     */
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if (!$command instanceof Command) {
            return;
        }

        // @igor-ignore - Nesting stack of the running commands (no request data).
        $this->runningCommands[] = $command;
        if (count($this->runningCommands) === 1 && !$this->messageInFlight) {
            $this->clearState();
        }
    }

    /**
     * End of a command: pops it and every command started after it, and remembers whether it was the outermost unit
     * (outside a Messenger message). A command that is not on the stack changes nothing.
     */
    public function onConsoleTerminateStart(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand();
        $index   = null;
        for ($i = count($this->runningCommands) - 1; $i >= 0; --$i) {
            if ($this->runningCommands[$i] === $command) {
                $index = $i;

                break;
            }
        }

        if ($index === null) {
            return;
        }

        // @igor-ignore - Nesting stack of the running commands (no request data).
        $this->runningCommands = array_slice($this->runningCommands, 0, $index);
        // @igor-ignore - Outermost command whose terminate is under way (cleared at the late priority).
        $this->terminatingOutermost = $index === 0 && !$this->messageInFlight ? $command : null;
    }

    /**
     * End of a command: clears only when the outermost command ends outside a Messenger message.
     */
    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        if (!$this->terminatingOutermost instanceof Command || $this->terminatingOutermost !== $event->getCommand()) {
            return;
        }

        // @igor-ignore - Outermost command whose terminate is under way (cleared at the late priority).
        $this->terminatingOutermost = null;
        $this->clearState();
    }

    /**
     * Forgets the console and Messenger bookkeeping (not the SEO state: see clearState()). Not tagged kernel.reset and
     * not relied on in worker mode.
     */
    public function reset(): void
    {
        $this->runningCommands      = [];
        $this->terminatingOutermost = null;
        $this->messageInFlight      = false;
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
