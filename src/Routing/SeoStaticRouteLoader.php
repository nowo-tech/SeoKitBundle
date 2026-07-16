<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Routing;

use Nowo\SeoKitBundle\Controller\SeoKitController;
use RuntimeException;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

use function is_array;
use function is_string;

/**
 * Registers sitemap.xml / robots.txt and optional static SEO paths from
 * `pages.*.path` when `controller` is set on the page config.
 *
 * Primary path i18n for apps is still Symfony routes + `_locale`; this loader
 * also helps when you declare pure CMS-like static URLs only in SEO config.
 */
final class SeoStaticRouteLoader extends Loader
{
    private bool $loaded = false;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
        ?string $env = null,
    ) {
        parent::__construct($env);
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new RuntimeException('SeoStaticRouteLoader already loaded.');
        }
        $this->loaded = true;

        $collection = new RouteCollection();
        $this->addSitemapAndRobotsRoutes($collection);

        $defaultLocale = (string) ($this->config['default_locale'] ?? 'en');

        foreach ($this->config['pages'] ?? [] as $routeName => $page) {
            if (!is_array($page) || !isset($page['controller']) || !is_string($page['controller'])) {
                continue;
            }
            $controller = $page['controller'];

            if (isset($page['path']) && is_string($page['path'])) {
                $collection->add(
                    (string) $routeName,
                    new Route($page['path'], ['_controller' => $controller, '_locale' => $defaultLocale]),
                );
            }

            foreach ($page['locales'] ?? [] as $locale => $localeCfg) {
                if (!is_array($localeCfg) || !isset($localeCfg['path']) || !is_string($localeCfg['path'])) {
                    continue;
                }
                $name = $routeName . '.' . $locale;
                $collection->add(
                    $name,
                    new Route($localeCfg['path'], ['_controller' => $controller, '_locale' => (string) $locale]),
                );
            }
        }

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'nowo_seo_kit';
    }

    private function addSitemapAndRobotsRoutes(RouteCollection $collection): void
    {
        $sitemap = is_array($this->config['sitemap'] ?? null) ? $this->config['sitemap'] : [];
        if (($sitemap['enabled'] ?? true) === true) {
            $path = is_string($sitemap['path'] ?? null) && $sitemap['path'] !== ''
                ? $sitemap['path']
                : '/sitemap.xml';
            $collection->add(
                'nowo_seo_kit_sitemap',
                new Route($path, ['_controller' => SeoKitController::class . '::sitemap'], methods: ['GET']),
            );
        }

        $robots = is_array($this->config['robots'] ?? null) ? $this->config['robots'] : [];
        if (($robots['enabled'] ?? true) === true) {
            $path = is_string($robots['path'] ?? null) && $robots['path'] !== ''
                ? $robots['path']
                : '/robots.txt';
            $collection->add(
                'nowo_seo_kit_robots',
                new Route($path, ['_controller' => SeoKitController::class . '::robots'], methods: ['GET']),
            );
        }
    }
}
