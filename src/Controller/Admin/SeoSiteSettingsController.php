<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Controller\Admin;

use Nowo\SeoKitBundle\Form\SeoSiteSettingsType;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Nowo\SeoKitBundle\Security\SeoKitAccessCheckerInterface;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProviderInterface;
use Nowo\SeoKitBundle\Ui\SeoKitPages;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function in_array;

/**
 * Site-wide SEO settings admin (identity, robots, organisation, per-locale wording) on the UiKit form scaffold.
 */
final class SeoSiteSettingsController extends AbstractController
{
    use SeoKitAdminAccessTrait;

    /**
     * @param list<string> $locales
     */
    public function __construct(
        private readonly SeoSiteSettingsRepository $repository,
        private readonly SeoSiteConfigProviderInterface $configProvider,
        SeoKitAccessCheckerInterface $accessChecker,
        private readonly SeoKitPages $pages = new SeoKitPages(),
        private readonly array $locales = ['en'],
        private readonly string $defaultLocale = 'en',
    ) {
        $this->accessChecker = $accessChecker;
    }

    #[Route('/settings/seo', name: 'nowo_seo_kit_admin_settings', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $this->denyUnlessAdmin();

        $settings = $this->repository->getOrCreate();
        $locales  = $this->orderedLocales();

        $form = $this->createForm(SeoSiteSettingsType::class, $settings, [
            'enabled_locales' => $locales,
            'default_locale'  => $this->defaultLocale,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->repository->save($settings);
            $this->configProvider->refresh();
            $this->addFlash('success', 'nowo_seo_kit.flash.settings_saved');

            return $this->redirectToRoute('nowo_seo_kit_admin_settings');
        }

        $view = $form->createView();

        return $this->render($this->pages->template('settings'), [
            'page'            => $this->pages->settingsPage($view, $this->container->get('router')),
            'form'            => $view,
            'settings'        => $settings,
            'enabled_locales' => $locales,
            'default_locale'  => $this->defaultLocale,
        ]);
    }

    /**
     * @return list<string>
     */
    private function orderedLocales(): array
    {
        $enabled = [];
        foreach ($this->locales as $locale) {
            $enabled[] = strtolower($locale);
        }
        $default = strtolower($this->defaultLocale);
        $ordered = [];
        if (in_array($default, $enabled, true)) {
            $ordered[] = $default;
        }
        foreach ($enabled as $locale) {
            if (!in_array($locale, $ordered, true)) {
                $ordered[] = $locale;
            }
        }

        return $ordered;
    }
}
