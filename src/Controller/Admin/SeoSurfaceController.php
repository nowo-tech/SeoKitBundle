<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Controller\Admin;

use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Form\SeoSurfaceLocaleFilterType;
use Nowo\SeoKitBundle\Form\SeoSurfaceType;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Nowo\SeoKitBundle\Security\SeoKitAccessCheckerInterface;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditor;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProviderInterface;
use Nowo\SeoKitBundle\Ui\SeoKitPages;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function in_array;
use function mb_strlen;

/**
 * Generic surface SEO overrides admin (list from audit subjects + edit by key/locale) on the UiKit list / form
 * scaffolds.
 */
final class SeoSurfaceController extends AbstractController
{
    use SeoKitAdminAccessTrait;

    /** Longest surface key the admin accepts (column length of SeoSurface::$surfaceKey). */
    public const MAX_SURFACE_KEY_LENGTH = 180;

    /**
     * @param list<string> $locales
     */
    public function __construct(
        private readonly SeoSurfaceRepository $surfaces,
        private readonly SeoAuditor $auditor,
        SeoKitAccessCheckerInterface $accessChecker,
        private readonly SeoKitPages $pages = new SeoKitPages(),
        private readonly ?SeoSiteConfigProviderInterface $configProvider = null,
        private readonly array $locales = ['en'],
        private readonly string $defaultLocale = 'en',
    ) {
        $this->accessChecker = $accessChecker;
    }

    #[Route('/admin/seo/surfaces', name: 'nowo_seo_kit_admin_surfaces', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyUnlessAdmin();
        $locale  = $this->requestedLocale($request);
        $locales = $this->orderedLocales();
        $rows    = $this->auditor->forLocale($locale);
        $filters = $this->container->get('form.factory')->createNamed('', SeoSurfaceLocaleFilterType::class, ['locale' => $locale], [
            'action'  => $this->generateUrl('nowo_seo_kit_admin_surfaces'),
            'locales' => $locales,
        ])->createView();

        return $this->render($this->pages->template('surfaces_index'), [
            'page'           => $this->pages->surfacesPage($rows, $locale, $filters, $this->container->get('router')),
            'locale'         => $locale,
            'locales'        => $locales,
            'rows'           => $rows,
            'site_indexable' => $this->configProvider?->get()->indexable ?? true,
        ]);
    }

    #[Route('/admin/seo/surfaces/{locale}/edit/{surfaceKey}', name: 'nowo_seo_kit_admin_surfaces_edit', requirements: ['locale' => '[a-z]{2}', 'surfaceKey' => '.+'], methods: ['GET', 'POST'])]
    public function edit(string $locale, string $surfaceKey, Request $request): Response
    {
        $this->denyUnlessAdmin();
        $surfaceKey = rawurldecode($surfaceKey);
        if (!in_array($locale, $this->orderedLocales(), true)) {
            throw $this->createNotFoundException('Locale is not enabled.');
        }
        if (mb_strlen($surfaceKey) > self::MAX_SURFACE_KEY_LENGTH) {
            throw $this->createNotFoundException('Surface key is too long.');
        }

        // Never persist on GET: a new override row is only written when a non-empty form is submitted.
        $surface = $this->surfaces->findOneByKeyAndLocale($surfaceKey, $locale)
            ?? (new SeoSurface())->setSurfaceKey($surfaceKey)->setLocale($locale);
        $form = $this->createForm(SeoSurfaceType::class, $surface);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->isEmpty($surface)) {
                if ($surface->getId() !== null) {
                    // @igor-ignore - HTTP handler delegates to services; no controller worker state.
                    $this->surfaces->remove($surface);
                }
            } else {
                $this->surfaces->save($surface);
            }

            $this->addFlash('success', 'nowo_seo_kit.flash.surface_saved');

            return $this->redirectToRoute('nowo_seo_kit_admin_surfaces', ['locale' => $locale]);
        }

        $view = $form->createView();

        return $this->render($this->pages->template('surfaces_form'), [
            'page'        => $this->pages->surfaceFormPage($surfaceKey, $locale, $view, $this->container->get('router')),
            'form'        => $view,
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
