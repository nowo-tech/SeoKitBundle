<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Worker;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\SeoKitBundle\Model\PageHeadInput;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Nowo\SeoKitBundle\SeoKitBundle;
use Nowo\SeoKitBundle\Service\PageHeadContext;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProvider;
use Nowo\SeoKitBundle\Service\SeoRuntime;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function assert;
use function sys_get_temp_dir;

/**
 * Kernel of the kept-alive boundary tests: Framework + Twig + Doctrine (in-memory SQLite) + SeoKit with persistence
 * and the page head enabled. Two pages ("/page/A", "/page/B") run {@see self::unitOfWork()}, the same unit the
 * Messenger handler and the console command of the test run.
 */
final class BoundariesTestKernel extends Kernel
{
    use MicroKernelTrait;

    public const RUNTIME     = 'test.boundaries.seo_runtime';
    public const PAGE_HEAD   = 'test.boundaries.page_head_context';
    public const SITE_CONFIG = 'test.boundaries.site_config_provider';
    public const SETTINGS    = 'test.boundaries.site_settings_repository';

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle(), new DoctrineBundle(), new SeoKitBundle()];
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/nowo-seo-kit-boundaries/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/nowo-seo-kit-boundaries/log';
    }

    /**
     * One unit of work for a tenant: reports the state it found, then sets its own private overrides and page head.
     *
     * @return array{overrides: array<string, mixed>, variables: array<string, mixed>, described: bool, siteName: string, title: string}
     */
    public static function unitOfWork(SeoRuntime $runtime, PageHeadContext $pageHead, SeoSiteConfigProvider $siteConfig, string $tenant): array
    {
        $found = [
            'overrides' => $runtime->getOverrides(),
            'variables' => $runtime->getVariables(),
            'described' => $pageHead->isDescribed(),
            'siteName'  => $siteConfig->get()->siteName,
        ];

        $runtime->set(['title' => 'Private offer for tenant ' . $tenant, 'noindex' => true]);
        $runtime->setVariables(['slug' => 'tenant-' . $tenant]);
        $pageHead->describe(new PageHeadInput(path: '/page/' . $tenant, locale: 'en', templateTitle: 'Tenant ' . $tenant));

        return $found + ['title' => $pageHead->pageHead()->title];
    }

    public function page(string $tenant): JsonResponse
    {
        $container = $this->getContainer();
        $runtime   = $container->get(self::RUNTIME);
        $pageHead  = $container->get(self::PAGE_HEAD);
        $site      = $container->get(self::SITE_CONFIG);
        assert($runtime instanceof SeoRuntime && $pageHead instanceof PageHeadContext && $site instanceof SeoSiteConfigProvider);

        return new JsonResponse(self::unitOfWork($runtime, $pageHead, $site, $tenant));
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'                => 'REDACTED-TEST-x',
            'test'                  => true,
            'http_method_override'  => false,
            'handle_all_throwables' => true,
            'php_errors'            => ['log' => true],
            'router'                => ['utf8' => true],
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm'  => ['entity_managers' => ['default' => ['auto_mapping' => true]]],
        ]);
        $container->extension('nowo_seo_kit', [
            'base_url'       => 'https://example.test',
            'default_locale' => 'en',
            'locales'        => ['en'],
            'pages'          => ['app_home' => ['title' => 'Home', 'path' => '/', 'in_sitemap' => true]],
            'persistence'    => ['enabled' => true],
        ]);
        // Quiet logger: the framework's Messenger failure listener logs every failing test message as critical.
        $container->services()->set('logger', NullLogger::class);
        $container->services()->alias(self::RUNTIME, SeoRuntime::class)->public();
        $container->services()->alias(self::PAGE_HEAD, PageHeadContext::class)->public();
        $container->services()->alias(self::SITE_CONFIG, SeoSiteConfigProvider::class)->public();
        $container->services()->alias(self::SETTINGS, SeoSiteSettingsRepository::class)->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('test_page', '/page/{tenant}')->controller('kernel::page');
    }
}
