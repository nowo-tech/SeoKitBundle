<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Controller\Admin;

use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Form\SeoSurfaceType;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditor;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProviderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function in_array;

/**
 * Generic surface SEO overrides admin (list from audit subjects + edit by key/locale).
 */
final class SeoSurfaceController extends AbstractController
{
    /**
     * @param list<string> $locales
     */
    public function __construct(
        private readonly SeoSurfaceRepository $surfaces,
        private readonly SeoAuditor $auditor,
        private readonly ?SeoSiteConfigProviderInterface $configProvider = null,
        private readonly array $locales = ['en'],
        private readonly string $defaultLocale = 'en',
        private readonly string $role = 'ROLE_ADMIN',
        private readonly string $indexTemplate = '@NowoSeoKitBundle/admin/surfaces_index.html.twig',
        private readonly string $formTemplate = '@NowoSeoKitBundle/admin/surfaces_form.html.twig',
    ) {
    }

    #[Route('/admin/seo/surfaces', name: 'nowo_seo_kit_admin_surfaces', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted($this->role);
        $locale = $this->requestedLocale($request);

        return $this->render($this->indexTemplate, [
            'locale'         => $locale,
            'locales'        => $this->orderedLocales(),
            'rows'           => $this->auditor->forLocale($locale),
            'site_indexable' => $this->configProvider?->get()->indexable ?? true,
        ]);
    }

    #[Route('/admin/seo/surfaces/{locale}/edit/{surfaceKey}', name: 'nowo_seo_kit_admin_surfaces_edit', requirements: ['locale' => '[a-z]{2}', 'surfaceKey' => '.+'], methods: ['GET', 'POST'])]
    public function edit(string $locale, string $surfaceKey, Request $request): Response
    {
        $this->denyAccessUnlessGranted($this->role);
        $surfaceKey = rawurldecode($surfaceKey);
        if (!in_array($locale, $this->orderedLocales(), true)) {
            throw $this->createNotFoundException('Locale is not enabled.');
        }

        $surface = $this->surfaces->getOrCreate($surfaceKey, $locale);
        $form    = $this->createForm(SeoSurfaceType::class, $surface);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->isEmpty($surface)) {
                if ($surface->getId() !== null) {
                    $this->surfaces->remove($surface);
                }
            } else {
                $this->surfaces->save($surface);
            }

            $this->addFlash('success', 'nowo_seo_kit.flash.surface_saved');

            return $this->redirectToRoute('nowo_seo_kit_admin_surfaces', ['locale' => $locale]);
        }

        return $this->render($this->formTemplate, [
            'form'        => $form,
            'surface'     => $surface,
            'surface_key' => $surfaceKey,
            'locale'      => $locale,
            'locales'     => $this->orderedLocales(),
        ]);
    }

    private function requestedLocale(Request $request): string
    {
        $requested = strtolower((string) $request->query->get('locale', ''));
        $enabled   = $this->orderedLocales();

        return in_array($requested, $enabled, true) ? $requested : ($enabled[0] ?? 'en');
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

    private function isEmpty(SeoSurface $seo): bool
    {
        return $seo->getMetaTitle() === null
            && $seo->getMetaDescription() === null
            && $seo->getMetaRobots() === null
            && $seo->getCanonicalOverride() === null
            && $seo->getOpenGraphImage() === null
            && $seo->getStructuredDataExtra() === null;
    }
}
