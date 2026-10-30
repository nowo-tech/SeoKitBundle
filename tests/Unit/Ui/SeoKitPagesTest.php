<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Ui;

use Nowo\SeoKitBundle\Security\AllowAllSeoKitAccessChecker;
use Nowo\SeoKitBundle\Security\SeoKitAccessCheckerInterface;
use Nowo\SeoKitBundle\Tests\Support\AdminPageFixtures;
use Nowo\SeoKitBundle\Ui\SeoKitAreaNav;
use Nowo\SeoKitBundle\Ui\SeoKitPages;
use Nowo\UiKitBundle\View\AreaNavItem;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\ListPage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormView;

use function array_map;

final class SeoKitPagesTest extends TestCase
{
    public function testTemplatesLayoutAndFormThemes(): void
    {
        $default = new SeoKitPages();
        self::assertSame(SeoKitPages::TEMPLATES['settings'], $default->template('settings'));
        self::assertSame('unknown', $default->template('unknown'));
        self::assertNull($default->layout());
        self::assertSame([SeoKitPages::DEFAULT_FORM_THEME], $default->formThemes());
        self::assertNull((new SeoKitPages(''))->layout());

        $custom = new SeoKitPages('admin/layout.html.twig', ['settings' => 'app/seo_settings.html.twig', 'surfaces_form' => ''], ['form/theme.html.twig']);
        self::assertSame('admin/layout.html.twig', $custom->layout());
        self::assertSame('app/seo_settings.html.twig', $custom->template('settings'));
        self::assertSame(SeoKitPages::TEMPLATES['surfaces_form'], $custom->template('surfaces_form'));
        self::assertSame(['form/theme.html.twig'], $custom->formThemes());

        $label = SeoKitPages::label('nowo_seo_kit.admin.save', ['%x%' => 1]);
        self::assertSame(['nowo_seo_kit.admin.save', 'NowoSeoKitBundle', ['%x%' => 1]], [$label->key, $label->domain, $label->params]);
    }

    public function testSettingsPageCarriesTheFormNavigationAndLayout(): void
    {
        $form = new FormView();
        $page = (new SeoKitPages('admin/layout.html.twig', areaNav: new SeoKitAreaNav(AdminPageFixtures::urls())))->settingsPage($form, AdminPageFixtures::urls());

        self::assertSame($form, $page->form);
        self::assertSame('admin/layout.html.twig', $page->layout);
        self::assertSame(SeoKitPages::BUNDLE, $page->bundle);
        self::assertSame('/settings/seo', $page->header->tabs?->active()?->href);
        self::assertSame([SeoKitPages::DEFAULT_FORM_THEME], $page->formThemes);
    }

    public function testNavigationTabsOnlyWithTwoPages(): void
    {
        $single = new SeoKitPages(areaNav: new SeoKitAreaNav(AdminPageFixtures::urls(), true, false));
        self::assertNull($single->settingsPage(new FormView(), AdminPageFixtures::urls())->header->tabs);

        $none = new SeoKitPages();
        self::assertNull($none->surfaceFormPage('page:home', 'en', new FormView(), AdminPageFixtures::urls())->header->tabs);
    }

    public function testSurfacesPageRowsColumnsAndEditActions(): void
    {
        $page = AdminPageFixtures::surfacesIndex()['page'];
        self::assertInstanceOf(ListPage::class, $page);

        self::assertSame(['surface', 'path', 'title', 'problems'], array_map(static fn (Column $column): string => $column->key, $page->columns));
        self::assertCount(3, $page->rows);
        self::assertSame('blog:post:42', $page->rows[1]['surface']);
        self::assertSame(['missing_title', 'missing_description', 'published_but_noindex'], $page->rows[1]['problems']);
        self::assertTrue($page->rows[0]['has_override']);
        self::assertNotNull($page->filters);
        self::assertSame('/admin/seo/surfaces', $page->header->tabs?->active()?->href);

        $actions = $page->table->rowActions($page->rows[1], 1);
        self::assertCount(1, $actions);
        self::assertSame('edit', $actions[0]->kind);
        self::assertSame('/admin/seo/surfaces/en/edit/blog:post:42', $actions[0]->href);
        self::assertSame([], $page->table->rowActions(['id' => 99]));

        self::assertInstanceOf(Label::class, $page->caption);
        self::assertSame(['%locale%' => 'EN'], $page->caption->params);
    }

    public function testSurfaceFormPageLinksBackToTheLocaleList(): void
    {
        $page = AdminPageFixtures::surfaceForm()['page'];
        self::assertInstanceOf(FormPage::class, $page);

        self::assertSame('/admin/seo/surfaces?locale=en', $page->cancelHref);
        self::assertSame('/admin/seo/surfaces?locale=en', $page->header->back?->href);
        self::assertInstanceOf(Label::class, $page->header->title);
        self::assertSame(['%surface%' => 'page:home'], $page->header->title->params);
    }

    public function testAreaNavProvider(): void
    {
        $nav = new SeoKitAreaNav(AdminPageFixtures::urls());

        self::assertNull($nav->areaNav('other'));
        $all = $nav->areaNav(SeoKitAreaNav::AREA);
        self::assertNotNull($all);
        self::assertSame(['/settings/seo', '/admin/seo/surfaces'], array_map(static fn (AreaNavItem $item): string => $item->href, $all->items));
        self::assertNull($all->active());
        self::assertSame('/settings/seo', $nav->areaNav(SeoKitAreaNav::AREA, ['current' => '/settings/seo'])?->active()?->href);
        self::assertNull($nav->areaNav(SeoKitAreaNav::AREA, ['current' => ''])?->active());

        $surfacesOnly = (new SeoKitAreaNav(AdminPageFixtures::urls(), false, true))->areaNav(SeoKitAreaNav::AREA);
        self::assertSame(['/admin/seo/surfaces'], array_map(static fn ($item): string => $item->href, $surfacesOnly->items ?? []));
    }

    public function testAreaNavIsHiddenFromUsersTheAccessCheckerRefuses(): void
    {
        $denied = new SeoKitAreaNav(AdminPageFixtures::urls(), accessChecker: new class implements SeoKitAccessCheckerInterface {
            public function canAccess(): bool
            {
                return false;
            }
        });
        self::assertNull($denied->areaNav(SeoKitAreaNav::AREA));

        $granted = new SeoKitAreaNav(AdminPageFixtures::urls(), accessChecker: new AllowAllSeoKitAccessChecker());
        self::assertCount(2, $granted->areaNav(SeoKitAreaNav::AREA)->items ?? []);
    }
}
