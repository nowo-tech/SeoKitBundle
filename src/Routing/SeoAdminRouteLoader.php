<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Routing;

use RuntimeException;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

use function rtrim;
use function strlen;
use function substr;

/**
 * Loads admin + API attribute routes when nowo_seo_kit.web_ui.enabled is true.
 *
 * With `web_ui.path_prefix` the admin pages move to `<prefix>/settings` and `<prefix>/surfaces…`; without it they
 * keep the 1.x paths (`/settings/seo`, `/admin/seo/surfaces…`). The JSON API stays under `/_nowo/seo/surfaces`.
 */
final class SeoAdminRouteLoader extends Loader
{
    /** 1.x path of the settings page (used without web_ui.path_prefix). */
    public const LEGACY_SETTINGS_PATH = '/settings/seo';

    /** 1.x path base of the surfaces pages (used without web_ui.path_prefix). */
    public const LEGACY_SURFACES_PATH = '/admin/seo/surfaces';

    private bool $loaded = false;

    public function __construct(
        private readonly bool $adminEnabled = false,
        private readonly bool $settingsEnabled = true,
        private readonly bool $surfacesEnabled = true,
        private readonly bool $apiEnabled = true,
        private readonly ?string $pathPrefix = null,
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new RuntimeException('Do not add the "nowo_seo_kit_admin" loader twice.');
        }
        // @igor-ignore - Route loader builds routes at compile/cache warm; not per-request mutation.
        $this->loaded = true;

        $collection = new RouteCollection();
        if (!$this->adminEnabled) {
            return $collection;
        }

        if ($this->settingsEnabled) {
            /** @var RouteCollection $settings */
            $settings = $this->import(__DIR__ . '/../Controller/Admin/SeoSiteSettingsController.php', 'attribute');
            $this->prefix($settings, self::LEGACY_SETTINGS_PATH, '/settings');
            $collection->addCollection($settings);
        }

        if ($this->surfacesEnabled) {
            /** @var RouteCollection $surfaces */
            $surfaces = $this->import(__DIR__ . '/../Controller/Admin/SeoSurfaceController.php', 'attribute');
            $this->prefix($surfaces, self::LEGACY_SURFACES_PATH, '/surfaces');
            $collection->addCollection($surfaces);
        }

        if ($this->apiEnabled) {
            /** @var RouteCollection $api */
            $api = $this->import(__DIR__ . '/../Controller/Api/', 'attribute');
            $collection->addCollection($api);
        }

        return $collection;
    }

    /**
     * Rewrites the 1.x path base of the imported routes to `<path_prefix><base>` (no-op without a prefix).
     */
    private function prefix(RouteCollection $routes, string $legacyBase, string $base): void
    {
        if ($this->pathPrefix === null || $this->pathPrefix === '') {
            return;
        }

        $newBase = rtrim($this->pathPrefix, '/') . $base;
        foreach ($routes as $route) {
            $route->setPath($newBase . substr($route->getPath(), strlen($legacyBase)));
        }
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'nowo_seo_kit_admin';
    }
}
