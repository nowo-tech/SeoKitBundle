<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\SeoKitBundle\SeoKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function sys_get_temp_dir;

/**
 * Kernel with the admin UI on (REQ-UI-002 functional test): Security (in-memory users, HTTP basic), Doctrine
 * (SQLite), FormKit, UiKit and SeoKit with `persistence` + `web_ui` under `/admin/seo`.
 */
final class SeoKitAdminTestKernel extends Kernel
{
    use MicroKernelTrait;

    public const DIR = '/nowo-seo-kit-admin-integration';

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new TwigExtraBundle(),
            new SecurityBundle(),
            new DoctrineBundle(),
            new NowoFormKitBundle(),
            new NowoUiKitBundle(),
            new SeoKitBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . self::DIR . '/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . self::DIR . '/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'                => 'integration-test',
            'test'                  => true,
            'http_method_override'  => false,
            'handle_all_throwables' => true,
            'php_errors'            => ['log' => true],
            'router'                => ['utf8' => true],
            'session'               => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection'       => true,
            'form'                  => ['csrf_protection' => true],
            'validation'            => true,
            'translator'            => ['default_path' => '%kernel.project_dir%/translations', 'fallbacks' => ['en']],
            'assets'                => true,
        ]);
        $container->extension('security', [
            'password_hashers' => [InMemoryUser::class => 'plaintext'],
            'providers'        => ['memory' => ['memory' => ['users' => [
                'admin'  => ['password' => 'admin', 'roles' => ['ROLE_ADMIN']],
                'editor' => ['password' => 'editor', 'roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['main' => ['lazy' => true, 'provider' => 'memory', 'http_basic' => null, 'stateless' => true]],
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///' . sys_get_temp_dir() . self::DIR . '/seo.sqlite'],
            'orm'  => ['auto_mapping' => false, 'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware'],
        ]);
        // Expected 401 / 403 responses are logged as errors by default: keep the test output clean.
        $container->services()->set('logger', NullLogger::class);
        $container->services()->set(StubAuditSubjectProvider::class)->tag('nowo_seo_kit.audit_subject_provider');
        $container->extension('nowo_seo_kit', [
            'base_url'       => 'https://example.test',
            'default_locale' => 'en',
            'locales'        => ['en', 'es'],
            'persistence'    => ['enabled' => true],
            'web_ui'         => ['enabled' => true, 'path_prefix' => '/admin/seo'],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'nowo_seo_kit_admin');
    }
}
