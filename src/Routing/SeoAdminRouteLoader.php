<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Routing;

use RuntimeException;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

/**
 * Loads admin + API attribute routes when nowo_seo_kit.admin.enabled is true.
 */
final class SeoAdminRouteLoader extends Loader
{
    private bool $loaded = false;

    public function __construct(
        private readonly bool $adminEnabled = false,
        private readonly bool $settingsEnabled = true,
        private readonly bool $surfacesEnabled = true,
        private readonly bool $apiEnabled = true,
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new RuntimeException('Do not add the "nowo_seo_kit_admin" loader twice.');
        }
        $this->loaded = true;

        $collection = new RouteCollection();
        if (!$this->adminEnabled) {
            return $collection;
        }

        if ($this->settingsEnabled) {
            /** @var RouteCollection $settings */
            $settings = $this->import(__DIR__ . '/../Controller/Admin/SeoSiteSettingsController.php', 'attribute');
            $collection->addCollection($settings);
        }

        if ($this->surfacesEnabled) {
            /** @var RouteCollection $surfaces */
            $surfaces = $this->import(__DIR__ . '/../Controller/Admin/SeoSurfaceController.php', 'attribute');
            $collection->addCollection($surfaces);
        }

        if ($this->apiEnabled) {
            /** @var RouteCollection $api */
            $api = $this->import(__DIR__ . '/../Controller/Api/', 'attribute');
            $collection->addCollection($api);
        }

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'nowo_seo_kit_admin';
    }
}
