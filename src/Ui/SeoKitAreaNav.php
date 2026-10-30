<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Ui;

use Nowo\SeoKitBundle\Security\SeoKitAccessCheckerInterface;
use Nowo\UiKitBundle\Navigation\AreaNavProviderInterface;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\AreaNavItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function is_string;

/**
 * Publishes the SeoKit admin navigation (SEO settings | Page SEO, the enabled pages only) to the UiKit area-nav
 * registry (REQ-UI-003 "Navigation integration"): a host menu (DashboardMenu) mounts it with
 * `nowo_ui_area_nav('seo_kit')`, only for users the admin access checker grants (no admin paths for others). The admin pages render the same navigation as page-header tabs (fallback for hosts
 * without DashboardMenu). `current` (an href) marks the active item. Stateless: safe in worker mode.
 */
final readonly class SeoKitAreaNav implements AreaNavProviderInterface
{
    public const AREA = 'seo_kit';

    /**
     * @param SeoKitAccessCheckerInterface|null $accessChecker when set, users it refuses get no navigation (null)
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private bool $settings = true,
        private bool $surfaces = true,
        private ?SeoKitAccessCheckerInterface $accessChecker = null,
    ) {
    }

    /**
     * @param array<string, mixed> $context `current` (href of the active item)
     */
    public function areaNav(string $area, array $context = []): ?AreaNav
    {
        if ($area !== self::AREA || ($this->accessChecker instanceof SeoKitAccessCheckerInterface && !$this->accessChecker->canAccess())) {
            return null;
        }

        $items = [];
        if ($this->settings) {
            $items[] = $this->item('nowo_seo_kit.admin.settings.title', 'nowo_seo_kit_admin_settings', 'settings');
        }
        if ($this->surfaces) {
            $items[] = $this->item('nowo_seo_kit.admin.surfaces.title', 'nowo_seo_kit_admin_surfaces', 'surfaces');
        }

        $nav     = new AreaNav($items, SeoKitPages::label('nowo_seo_kit.admin.area_nav'));
        $current = $context['current'] ?? null;

        return is_string($current) && $current !== '' ? $nav->withActive($current) : $nav;
    }

    private function item(string $label, string $route, string $testId): AreaNavItem
    {
        return new AreaNavItem(SeoKitPages::label($label), $this->urlGenerator->generate($route), false, ['data-testid' => 'seo-kit-nav-' . $testId]);
    }
}
