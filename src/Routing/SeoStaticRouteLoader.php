<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Routing;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

use function is_array;
use function is_string;

/**
 * Registers optional static SEO paths from `pages.*.path` / per-locale paths
 * when `controller` is set on the page config (optional advanced use).
 *
 * Primary path i18n for apps is still Symfony routes + `_locale`; this loader
 * helps when you declare pure CMS-like static URLs only in SEO config.
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
            throw new \RuntimeException('SeoStaticRouteLoader already loaded.');
        }
        $this->loaded = true;

        $collection = new RouteCollection();
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
                $name = (string) $routeName.'.'.$locale;
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
}
