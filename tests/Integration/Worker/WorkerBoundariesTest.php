<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Worker;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
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
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Worker;

use function is_array;
use function json_decode;
use function json_encode;
use function sprintf;

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

    private function dispatcher(): EventDispatcher
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcher::class, $dispatcher);

        return $dispatcher;
    }
}
