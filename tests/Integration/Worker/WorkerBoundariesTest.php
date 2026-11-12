<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Worker;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\SeoKitBundle\Model\PageHeadInput;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Nowo\SeoKitBundle\Service\PageHeadContext;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProvider;
use Nowo\SeoKitBundle\Service\SeoRuntime;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Worker;
use Throwable;

use function is_array;
use function json_decode;
use function json_encode;
use function sprintf;

use const PHP_INT_MIN;

/**
 * Kept-alive kernel, services resetter never called (a host that removes or bypasses services_resetter,
 * `messenger:consume --no-reset`, long-running console processes): one booted kernel, the same SeoRuntime,
 * PageHeadContext and SeoSiteConfigProvider instances, many units of work for two tenants in alternation.
 *
 * Each unit first records what it found (runtime overrides and variables, described page head, site name), then
 * sets its own tenant's private overrides and page head, as a controller or an e-mail handler would. Between units,
 * "another worker" renames the site in the database and invalidates the shared cache entry (what the admin's
 * refresh() does), so a unit that still reads the memo of the previous one sees a stale site name.
 *
 * N = 500 per path: HTTP through http_kernel (no Kernel::handle(), so no resetter; two pages), Messenger through a real
 * Worker without ResetServicesListener (every 7th handler fails), and console Application runs. Memory margin: 1 MiB
 * after a 50-unit warm-up.
 */
final class WorkerBoundariesTest extends KernelTestCase
{
    private const ITERATIONS    = 500;
    private const WARMUP        = 50;
    private const MEMORY_MARGIN = 1024 * 1024;

    /** @var list<string> */
    private array $leaks = [];

    private int $memoryAtWarmup = 0;

    private int $memoryAtEnd = 0;

    protected static function getKernelClass(): string
    {
        return BoundariesTestKernel::class;
    }

    public function testManyRequestsToTwoPagesKeepTenantsIsolatedAndMemoryFlat(): void
    {
        $this->boot();
        $http = self::getContainer()->get('http_kernel');
        self::assertInstanceOf(HttpKernelInterface::class, $http);
        self::assertInstanceOf(TerminableInterface::class, $http);

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $this->renameSite($i);
            $this->memoryMark($i);
            $tenant   = $this->tenantFor($i);
            $request  = Request::create('/page/' . $tenant);
            $response = $http->handle($request);
            self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
            /** @var array{overrides: array<string, mixed>, variables: array<string, mixed>, described: bool, siteName: string, title: string} $found */
            $found = json_decode((string) $response->getContent(), true);
            $this->check($found, $tenant, $i);
            $http->terminate($request, $response);
        }

        $this->assertNoLeakAndFlatMemory('HTTP requests');
    }

    public function testManyMessengerMessagesKeepTenantsIsolatedAndMemoryFlat(): void
    {
        $this->boot();
        [$runtime, $pageHead, $site] = $this->services();
        $dispatcher                  = $this->dispatcher();

        foreach ($dispatcher->getListeners(WorkerRunningEvent::class) as $listener) {
            self::assertFalse(
                is_array($listener) && $listener[0] instanceof ResetServicesListener,
                'The worker must run without ResetServicesListener (messenger:consume --no-reset).',
            );
        }

        $receiver = new TenantPageReceiver(self::ITERATIONS);
        $bus      = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            TenantPageMessage::class => [function (TenantPageMessage $message) use ($runtime, $pageHead, $site): void {
                $this->memoryMark($message->index);
                $found = BoundariesTestKernel::unitOfWork($runtime, $pageHead, $site, $message->tenant);
                $this->check($found, $message->tenant, $message->index);
                // The next message runs after another worker's site edit.
                $this->renameSite($message->index + 1);
                if ($message->fail) {
                    throw new RuntimeException('Mailer down for tenant ' . $message->tenant);
                }
            }],
        ]))]);

        $this->renameSite(0);
        $worker     = new Worker(['async' => $receiver], $bus, $dispatcher);
        $stopOnIdle = static function (WorkerRunningEvent $event): void {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        };
        $dispatcher->addListener(WorkerRunningEvent::class, $stopOnIdle);

        try {
            $worker->run(['sleep' => 0]);
        } finally {
            $dispatcher->removeListener(WorkerRunningEvent::class, $stopOnIdle);
        }

        self::assertSame(self::ITERATIONS, $receiver->acknowledged + $receiver->rejected);
        self::assertGreaterThan(0, $receiver->rejected);
        $this->assertNoLeakAndFlatMemory('Messenger messages');
        $this->assertCleared($runtime, $pageHead, 'after the last WorkerMessageHandled / Failed');
    }

    public function testManyConsoleRunsKeepTenantsIsolatedAndMemoryFlat(): void
    {
        $this->boot();
        [$runtime, $pageHead, $site] = $this->services();

        $command = (new Command('app:mail-tenant'))
            ->addArgument('tenant', InputArgument::REQUIRED)
            ->addArgument('index', InputArgument::REQUIRED)
            ->setCode(function (InputInterface $input, OutputInterface $output) use ($runtime, $pageHead, $site): int {
                $tenant = (string) $input->getArgument('tenant');
                $index  = (int) $input->getArgument('index');
                $this->check(BoundariesTestKernel::unitOfWork($runtime, $pageHead, $site, $tenant), $tenant, $index);

                return Command::SUCCESS;
            });

        $application = new Application('worker-test');
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->setDispatcher($this->dispatcher());
        $application->setCommandLoader(new FactoryCommandLoader(['app:mail-tenant' => static fn (): Command => $command]));

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $this->renameSite($i);
            $this->memoryMark($i);
            $exitCode = $application->run(
                new ArrayInput(['command' => 'app:mail-tenant', 'tenant' => $this->tenantFor($i), 'index' => (string) $i]),
                new NullOutput(),
            );
            self::assertSame(Command::SUCCESS, $exitCode);
            $this->assertCleared($runtime, $pageHead, sprintf('after console.terminate of run %d', $i));
        }

        $this->assertNoLeakAndFlatMemory('console command runs');
    }

    /**
     * MI-S-L2: a command run in-process from another command (Application::doRun()) fires console.command /
     * console.terminate, but must not clear the overrides and page head of the outer command; the outermost command
     * still starts and ends clean.
     */
    public function testNestedCommandKeepsTheOuterCommandsState(): void
    {
        $this->boot();
        [$runtime, $pageHead] = $this->services();
        $application          = $this->application();

        $seenAfterInner = [];
        $inner          = (new Command('app:inner'))->setCode(static fn (): int => Command::SUCCESS);
        $outer          = (new Command('app:outer'))->setCode(static function (InputInterface $input, OutputInterface $output) use ($application, $runtime, $pageHead, &$seenAfterInner): int {
            $runtime->set(['title' => 'Outer command mail']);
            $pageHead->describe(new PageHeadInput(path: '/outer', locale: 'en', templateTitle: 'Outer'));
            $application->doRun(new ArrayInput(['command' => 'app:inner']), $output);
            $application->doRun(new ArrayInput(['command' => 'app:inner']), $output);
            $seenAfterInner = [$runtime->getOverrides(), $pageHead->isDescribed()];

            return Command::SUCCESS;
        });
        $application->setCommandLoader(new FactoryCommandLoader([
            'app:outer' => static fn (): Command => $outer,
            'app:inner' => static fn (): Command => $inner,
        ]));

        for ($i = 0; $i < 2; ++$i) {
            self::assertSame(Command::SUCCESS, $application->run(new ArrayInput(['command' => 'app:outer']), new NullOutput()));
            self::assertSame([['title' => 'Outer command mail'], true], $seenAfterInner, 'A nested command cleared the outer command\'s state.');
            $this->assertCleared($runtime, $pageHead, 'after the outermost console.terminate');
        }
    }

    /**
     * MI-S-L2: a command run in-process from a Messenger handler must not clear the handler's state; the message
     * boundaries still clear it.
     */
    public function testCommandRunFromAMessageHandlerKeepsTheHandlersState(): void
    {
        $this->boot();
        [$runtime, $pageHead] = $this->services();
        $dispatcher           = $this->dispatcher();
        $application          = $this->application();
        $inner                = (new Command('app:inner'))->setCode(static fn (): int => Command::SUCCESS);
        $application->setCommandLoader(new FactoryCommandLoader(['app:inner' => static fn (): Command => $inner]));

        $seenAfterCommand = [];
        $bus              = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            TenantPageMessage::class => [static function (TenantPageMessage $message) use ($application, $runtime, &$seenAfterCommand): void {
                $runtime->set(['title' => 'Mail for tenant ' . $message->tenant]);
                $application->doRun(new ArrayInput(['command' => 'app:inner']), new NullOutput());
                $seenAfterCommand[] = $runtime->getOverrides();
            }],
        ]))]);

        $worker     = new Worker(['async' => new TenantPageReceiver(4)], $bus, $dispatcher);
        $stopOnIdle = static function (WorkerRunningEvent $event): void {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        };
        $dispatcher->addListener(WorkerRunningEvent::class, $stopOnIdle);

        try {
            $worker->run(['sleep' => 0]);
        } finally {
            $dispatcher->removeListener(WorkerRunningEvent::class, $stopOnIdle);
        }

        self::assertSame([
            ['title' => 'Mail for tenant A'],
            ['title' => 'Mail for tenant B'],
            ['title' => 'Mail for tenant A'],
            ['title' => 'Mail for tenant B'],
        ], $seenAfterCommand, 'A command run from the handler cleared its state.');
        $this->assertCleared($runtime, $pageHead, 'after the last message');

        // After the worker stopped, a standalone command clears again.
        $runtime->set(['title' => 'Left over']);
        $application->run(new ArrayInput(['command' => 'app:inner']), new NullOutput());
        $this->assertCleared($runtime, $pageHead, 'after a standalone command once the worker stopped');
    }

    /**
     * Audit NM (a): a console.terminate listener of a nested command that throws no longer leaves the console
     * bookkeeping unbalanced: the outer command and every later outermost command still end clean.
     */
    public function testThrowingTerminateListenerOfANestedCommandKeepsTheOuterBoundary(): void
    {
        $this->boot();
        [$runtime, $pageHead] = $this->services();
        $dispatcher           = $this->dispatcher();
        $throwing             = static function (ConsoleTerminateEvent $event): void {
            if ($event->getCommand()?->getName() === 'app:inner') {
                throw new RuntimeException('terminate listener failed');
            }
        };
        $dispatcher->addListener(ConsoleEvents::TERMINATE, $throwing, 0);

        try {
            for ($i = 0; $i < 2; ++$i) {
                self::assertSame([['title' => 'Outer command mail'], true], $this->runOuterWithNestedRun('app:inner'), 'A nested command cleared the outer command\'s state.');
                $this->assertCleared($runtime, $pageHead, 'after the outermost console.terminate');
            }
        } finally {
            $dispatcher->removeListener(ConsoleEvents::TERMINATE, $throwing);
        }
    }

    /**
     * Audit NM (c): an unknown nested command (only console.error, no command / terminate) never ends the outer unit.
     */
    public function testUnknownNestedCommandKeepsTheOuterUnit(): void
    {
        $this->boot();
        [$runtime, $pageHead] = $this->services();

        self::assertSame([['title' => 'Outer command mail'], true], $this->runOuterWithNestedRun('app:does-not-exist'), 'An unknown nested command cleared the outer state.');
        $this->assertCleared($runtime, $pageHead, 'after the outermost console.terminate');
    }

    /**
     * Audit NM (d): a console.command listener above 4096 that stops propagation in a nested command no longer lets
     * that command's terminate clear the outer unit (overrides, page head, private entity manager) mid-run.
     */
    public function testStoppedNestedCommandEventKeepsTheOuterUnit(): void
    {
        $this->boot();
        [$runtime, $pageHead] = $this->services();
        $dispatcher           = $this->dispatcher();
        $stopping             = static function (ConsoleCommandEvent $event): void {
            if ($event->getCommand()?->getName() === 'app:inner') {
                $event->stopPropagation();
            }
        };
        $dispatcher->addListener(ConsoleEvents::COMMAND, $stopping, 10000);

        try {
            self::assertSame([['title' => 'Outer command mail'], true], $this->runOuterWithNestedRun('app:inner'), 'A nested command cleared the outer state mid-run.');
            $this->assertCleared($runtime, $pageHead, 'after the outermost console.terminate');
        } finally {
            $dispatcher->removeListener(ConsoleEvents::COMMAND, $stopping);
        }
    }

    /**
     * Audit NL2-v: a command that runs itself in-process (same Command object, new input) whose inner run fails and
     * whose error listener throws: the outer run keeps its state until it ends clean, and so does the next command.
     */
    public function testSelfRecursiveCommandWithAThrowingErrorListener(): void
    {
        $this->boot();
        [$runtime, $pageHead] = $this->services();
        $dispatcher           = $this->dispatcher();
        $application          = $this->application();
        $throwing             = static function (): never {
            throw new RuntimeException('error listener failed');
        };
        $depth     = 0;
        $seen      = [];
        $recursive = (new Command('app:rec'))->setCode(static function (InputInterface $input, OutputInterface $output) use ($application, $runtime, $dispatcher, $throwing, &$depth, &$seen): int {
            if (++$depth > 1) {
                throw new RuntimeException('inner run failed');
            }
            $runtime->set(['title' => 'Outer command mail']);
            $dispatcher->addListener(ConsoleEvents::ERROR, $throwing);
            try {
                $application->run(new ArrayInput(['command' => 'app:rec']), $output);
            } catch (Throwable) {
                // The inner run failed and its error listener threw.
            } finally {
                $dispatcher->removeListener(ConsoleEvents::ERROR, $throwing);
            }
            $seen = $runtime->getOverrides();

            return Command::SUCCESS;
        });
        $application->setCommandLoader(new FactoryCommandLoader(['app:rec' => static fn (): Command => $recursive]));

        self::assertSame(Command::SUCCESS, $application->run(new ArrayInput(['command' => 'app:rec']), new NullOutput()));
        self::assertSame(['title' => 'Outer command mail'], $seen, 'The failed inner run cleared the outer state.');
        $this->assertCleared($runtime, $pageHead, 'after the outer run');

        self::assertSame([['title' => 'Outer command mail'], true], $this->runOuterWithNestedRun('app:inner'));
        $this->assertCleared($runtime, $pageHead, 'after the next outermost command');
    }

    /**
     * Audit NL2-v: an error listener at PHP_INT_MIN throws, so the failing outermost command never terminates; its
     * frame is dropped at the next command, which is the outermost unit again (clears at its start and end).
     */
    public function testErrorListenerAtIntMinThrowingNeverKeepsTheProcessInsideACommand(): void
    {
        $this->boot();
        [$runtime, $pageHead] = $this->services();
        $dispatcher           = $this->dispatcher();
        $application          = $this->application();
        $throwing             = static function (): never {
            throw new RuntimeException('error listener failed');
        };
        $failing = (new Command('app:fail'))->setCode(static function () use ($runtime): never {
            $runtime->set(['title' => 'Failed command mail']);

            throw new RuntimeException('command failed');
        });
        $application->setCommandLoader(new FactoryCommandLoader(['app:fail' => static fn (): Command => $failing]));
        $dispatcher->addListener(ConsoleEvents::ERROR, $throwing, PHP_INT_MIN);

        try {
            $application->run(new ArrayInput(['command' => 'app:fail']), new NullOutput());
            self::fail('The error listener throws.');
        } catch (RuntimeException) {
        } finally {
            $dispatcher->removeListener(ConsoleEvents::ERROR, $throwing);
        }

        self::assertSame([['title' => 'Outer command mail'], true], $this->runOuterWithNestedRun('app:inner'), 'The next command starts clean.');
        $this->assertCleared($runtime, $pageHead, 'after the next outermost command');
    }

    /**
     * Audit NL2-v: a WorkerMessageReceivedEvent listener throws inside an in-process consume command (no handled /
     * failed / stopped follows); the command's frame restores the "no message" state when it ends.
     */
    public function testThrowingMessageReceivedListenerInsideACommandDoesNotLeakTheMessageFlag(): void
    {
        $this->boot();
        [$runtime, $pageHead] = $this->services();
        $dispatcher           = $this->dispatcher();
        $application          = $this->application();
        $throwing             = static function (): never {
            throw new RuntimeException('received listener failed');
        };
        $consume = (new Command('app:consume'))->setCode(static function () use ($dispatcher, $throwing): int {
            $dispatcher->addListener(WorkerMessageReceivedEvent::class, $throwing);
            try {
                $dispatcher->dispatch(new WorkerMessageReceivedEvent(new Envelope(new TenantPageMessage('A', 0, false)), 'async'));
            } catch (RuntimeException) {
            } finally {
                $dispatcher->removeListener(WorkerMessageReceivedEvent::class, $throwing);
            }

            return Command::SUCCESS;
        });
        $application->setCommandLoader(new FactoryCommandLoader(['app:consume' => static fn (): Command => $consume]));

        self::assertSame(Command::SUCCESS, $application->run(new ArrayInput(['command' => 'app:consume']), new NullOutput()));
        self::assertSame([['title' => 'Outer command mail'], true], $this->runOuterWithNestedRun('app:inner'));
        $this->assertCleared($runtime, $pageHead, 'after the next outermost command (no message in flight)');
    }

    /**
     * Audit NL2-v2: a message handler runs a command that fails and whose console.error listener throws (no
     * terminate); the message fails and the worker stops: no message is stuck in flight, the next command clears.
     */
    public function testFailedCommandInsideAMessageNeverLeavesTheMessageInFlight(): void
    {
        $this->boot();
        [$runtime, $pageHead] = $this->services();
        $dispatcher           = $this->dispatcher();
        $application          = $this->application();
        $envelope             = new Envelope(new TenantPageMessage('A', 0, true));
        $throwing             = static function (): never {
            throw new RuntimeException('error listener failed');
        };
        $failing = (new Command('app:fail'))->setCode(static function (): never {
            throw new RuntimeException('command failed');
        });
        $application->setCommandLoader(new FactoryCommandLoader(['app:fail' => static fn (): Command => $failing]));

        $dispatcher->dispatch(new WorkerMessageReceivedEvent($envelope, 'async'));
        $dispatcher->addListener(ConsoleEvents::ERROR, $throwing);
        try {
            $application->run(new ArrayInput(['command' => 'app:fail']), new NullOutput());
            self::fail('The error listener throws.');
        } catch (RuntimeException) {
        } finally {
            $dispatcher->removeListener(ConsoleEvents::ERROR, $throwing);
        }
        $dispatcher->dispatch(new WorkerMessageFailedEvent($envelope, 'async', new RuntimeException('handler failed')));
        $dispatcher->dispatch(new WorkerStoppedEvent($this->createStub(Worker::class)));

        self::assertSame([['title' => 'Outer command mail'], true], $this->runOuterWithNestedRun('app:inner'));
        $this->assertCleared($runtime, $pageHead, 'after the next outermost command (no message stuck in flight)');
    }

    /**
     * Runs app:outer through a real console Application: it sets overrides and a page head, runs $nested in-process
     * (a failure of the nested run is swallowed) and returns what it sees right after the nested run.
     *
     * @return list<array<string, mixed>|bool>
     */
    private function runOuterWithNestedRun(string $nested): array
    {
        [$runtime, $pageHead] = $this->services();
        $application          = $this->application();

        $seen  = [];
        $inner = (new Command('app:inner'))->setCode(static fn (): int => Command::SUCCESS);
        $outer = (new Command('app:outer'))->setCode(static function (InputInterface $input, OutputInterface $output) use ($application, $runtime, $pageHead, $nested, &$seen): int {
            $runtime->set(['title' => 'Outer command mail']);
            $pageHead->describe(new PageHeadInput(path: '/outer', locale: 'en', templateTitle: 'Outer'));
            try {
                $application->doRun(new ArrayInput(['command' => $nested]), $output);
            } catch (Throwable) {
                // The nested run failed (throwing listener, unknown command); the outer command goes on.
            }
            $seen = [$runtime->getOverrides(), $pageHead->isDescribed()];

            return Command::SUCCESS;
        });
        $application->setCommandLoader(new FactoryCommandLoader([
            'app:outer' => static fn (): Command => $outer,
            'app:inner' => static fn (): Command => $inner,
        ]));

        self::assertSame(Command::SUCCESS, $application->run(new ArrayInput(['command' => 'app:outer']), new NullOutput()));

        return $seen;
    }

    private function application(): Application
    {
        $application = new Application('worker-test');
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->setDispatcher($this->dispatcher());

        return $application;
    }

    private function boot(): void
    {
        self::bootKernel(['debug' => false]);
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $this->leaks          = [];
        $this->memoryAtWarmup = 0;
        $this->memoryAtEnd    = 0;
    }

    /**
     * Another worker edits the site settings (database + shared cache invalidation, as the admin's refresh() does).
     */
    private function renameSite(int $index): void
    {
        $repository = self::getContainer()->get(BoundariesTestKernel::SETTINGS);
        self::assertInstanceOf(SeoSiteSettingsRepository::class, $repository);
        $settings = $repository->getOrCreate();
        $settings->setSiteName('Site revision ' . $index);
        $repository->save($settings);

        $cache = self::getContainer()->get('cache.app');
        self::assertInstanceOf(CacheItemPoolInterface::class, $cache);
        $cache->deleteItem('nowo_seo_kit.site_config');
    }

    /**
     * @param array{overrides: array<string, mixed>, variables: array<string, mixed>, described: bool, siteName: string, title: string} $found
     */
    private function check(array $found, string $tenant, int $index): void
    {
        $expected = ['overrides' => [], 'variables' => [], 'described' => false, 'siteName' => 'Site revision ' . $index];
        $actual   = ['overrides' => $found['overrides'], 'variables' => $found['variables'], 'described' => $found['described'], 'siteName' => $found['siteName']];
        if ($actual !== $expected) {
            $this->leaks[] = sprintf('unit %d (tenant %s) started with %s', $index, $tenant, json_encode($actual));
        }
        if ($found['title'] !== 'Tenant ' . $tenant) {
            $this->leaks[] = sprintf('unit %d (tenant %s) rendered title %s', $index, $tenant, $found['title']);
        }
    }

    private function memoryMark(int $index): void
    {
        if ($index === self::WARMUP) {
            gc_collect_cycles();
            $this->memoryAtWarmup = memory_get_usage();
        }
        if ($index === self::ITERATIONS - 1) {
            gc_collect_cycles();
            $this->memoryAtEnd = memory_get_usage();
        }
    }

    private function assertNoLeakAndFlatMemory(string $path): void
    {
        self::assertSame([], $this->leaks, sprintf('SEO state leaked between %s.', $path));
        self::assertGreaterThan(0, $this->memoryAtWarmup);
        self::assertLessThan(self::MEMORY_MARGIN, $this->memoryAtEnd - $this->memoryAtWarmup, sprintf('Memory grew across %s.', $path));
    }

    private function assertCleared(SeoRuntime $runtime, PageHeadContext $pageHead, string $when): void
    {
        self::assertSame([], $runtime->getOverrides(), 'Runtime overrides survive ' . $when);
        self::assertSame([], $runtime->getVariables(), 'Runtime variables survive ' . $when);
        self::assertFalse($pageHead->isDescribed(), 'Page head survives ' . $when);
    }

    /**
     * @return array{0: SeoRuntime, 1: PageHeadContext, 2: SeoSiteConfigProvider}
     */
    private function services(): array
    {
        $container = self::getContainer();
        $runtime   = $container->get(BoundariesTestKernel::RUNTIME);
        $pageHead  = $container->get(BoundariesTestKernel::PAGE_HEAD);
        $site      = $container->get(BoundariesTestKernel::SITE_CONFIG);
        self::assertInstanceOf(SeoRuntime::class, $runtime);
        self::assertInstanceOf(PageHeadContext::class, $pageHead);
        self::assertInstanceOf(SeoSiteConfigProvider::class, $site);

        return [$runtime, $pageHead, $site];
    }

    private function tenantFor(int $index): string
    {
        return $index % 2 === 0 ? 'A' : 'B';
    }

    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        return $dispatcher;
    }
}
