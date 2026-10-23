<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\PageFormType;
use App\Form\SlugFormType;
use App\Form\SlugRouteFormType;
use App\Service\SeoConfigStore;
use Nowo\SeoKitBundle\Attribute\Seo;
use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function sprintf;

#[Route(path: '/admin/seo', name: 'admin_seo_')]
#[Seo(title: 'SEO Admin', description: 'Demo CRUD for SEO config', robots: 'noindex,nofollow')]
final class SeoAdminController extends AbstractController
{
    public function __construct(
        private readonly SeoConfigStore $store,
    ) {
    }

    private function paths(): SeoPathBuilder
    {
        // Build from YAML file so admin URLs reflect edits after cache clear.
        return new SeoPathBuilder($this->store->root());
    }

    #[Route(path: '', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $root    = $this->store->root();
        $locales = is_array($root['locales'] ?? null) ? $root['locales'] : ['en'];

        return $this->render('admin/seo/dashboard.html.twig', [
            'root'        => $root,
            'pages'       => $this->store->pages(),
            'slug_routes' => $this->store->slugRoutes(),
            'slugs'       => $this->store->slugs(),
            'locales'     => $locales,
            'config_file' => $this->store->configPath(),
            'page_urls'   => $this->buildPageUrls($this->store->pages(), $locales),
            'slug_urls'   => $this->buildSlugUrls($this->store->slugs(), $locales),
        ]);
    }

    #[Route(path: '/pages', name: 'pages', methods: ['GET'])]
    public function pages(): Response
    {
        $root    = $this->store->root();
        $locales = is_array($root['locales'] ?? null) ? $root['locales'] : ['en'];
        $pages   = $this->store->pages();

        $deleteForms = [];
        foreach (array_keys($pages) as $route) {
            $deleteForms[$route] = $this->deleteForm($this->generateUrl('admin_seo_page_delete', ['route' => $route]))->createView();
        }

        return $this->render('admin/seo/pages.html.twig', [
            'pages'        => $pages,
            'delete_forms' => $deleteForms,
            'locales'      => $locales,
            'page_urls'    => $this->buildPageUrls($pages, $locales),
        ]);
    }

    #[Route(path: '/pages/new', name: 'page_new', methods: ['GET', 'POST'])]
    public function pageNew(Request $request): Response
    {
        return $this->handlePageForm($request, null);
    }

    #[Route(path: '/pages/{route}', name: 'page_edit', methods: ['GET', 'POST'], requirements: ['route' => '[a-zA-Z0-9_.-]+'])]
    public function pageEdit(Request $request, string $route): Response
    {
        $pages = $this->store->pages();
        if (!isset($pages[$route])) {
            throw $this->createNotFoundException(sprintf('Page "%s" not found.', $route));
        }

        return $this->handlePageForm($request, $route);
    }

    #[Route(path: '/pages/{route}/delete', name: 'page_delete', methods: ['POST'], requirements: ['route' => '[a-zA-Z0-9_.-]+'])]
    public function pageDelete(Request $request, string $route): Response
    {
        if (!$this->deleteSubmitted($request, $this->generateUrl('admin_seo_page_delete', ['route' => $route]))) {
            return $this->redirectToRoute('admin_seo_pages');
        }
        $this->store->deletePage($route);
        $this->addFlash('success', sprintf('Page "%s" deleted.', $route));

        return $this->redirectToRoute('admin_seo_pages');
    }

    #[Route(path: '/slug-routes', name: 'slug_routes', methods: ['GET'])]
    public function slugRoutes(): Response
    {
        $slugRoutes  = $this->store->slugRoutes();
        $deleteForms = [];
        foreach (array_keys($slugRoutes) as $route) {
            $deleteForms[$route] = $this->deleteForm($this->generateUrl('admin_seo_slug_route_delete', ['route' => $route]))->createView();
        }

        return $this->render('admin/seo/slug_routes.html.twig', [
            'slug_routes'  => $slugRoutes,
            'delete_forms' => $deleteForms,
            'locales'      => $this->store->root()['locales'] ?? ['en'],
        ]);
    }

    #[Route(path: '/slug-routes/new', name: 'slug_route_new', methods: ['GET', 'POST'])]
    public function slugRouteNew(Request $request): Response
    {
        return $this->handleSlugRouteForm($request, null);
    }

    #[Route(path: '/slug-routes/{route}', name: 'slug_route_edit', methods: ['GET', 'POST'], requirements: ['route' => '[a-zA-Z0-9_.-]+'])]
    public function slugRouteEdit(Request $request, string $route): Response
    {
        $routes = $this->store->slugRoutes();
        if (!isset($routes[$route])) {
            throw $this->createNotFoundException(sprintf('Slug route "%s" not found.', $route));
        }

        return $this->handleSlugRouteForm($request, $route);
    }

    #[Route(path: '/slug-routes/{route}/delete', name: 'slug_route_delete', methods: ['POST'], requirements: ['route' => '[a-zA-Z0-9_.-]+'])]
    public function slugRouteDelete(Request $request, string $route): Response
    {
        if (!$this->deleteSubmitted($request, $this->generateUrl('admin_seo_slug_route_delete', ['route' => $route]))) {
            return $this->redirectToRoute('admin_seo_slug_routes');
        }
        $this->store->deleteSlugRoute($route);
        $this->addFlash('success', sprintf('Slug route "%s" deleted (and its slugs).', $route));

        return $this->redirectToRoute('admin_seo_slug_routes');
    }

    #[Route(path: '/slugs', name: 'slugs', methods: ['GET'])]
    public function slugs(): Response
    {
        $root    = $this->store->root();
        $locales = is_array($root['locales'] ?? null) ? $root['locales'] : ['en'];
        $slugs   = $this->store->slugs();

        /** @var array<string, array<string, FormView>> $deleteForms */
        $deleteForms = [];
        foreach ($slugs as $route => $bySlug) {
            foreach (array_keys(is_array($bySlug) ? $bySlug : []) as $slug) {
                $deleteForms[$route][$slug] = $this->deleteForm(
                    $this->generateUrl('admin_seo_slug_delete', ['route' => $route, 'slug' => $slug]),
                )->createView();
            }
        }

        return $this->render('admin/seo/slugs.html.twig', [
            'slugs'        => $slugs,
            'delete_forms' => $deleteForms,
            'locales'      => $locales,
            'slug_urls'    => $this->buildSlugUrls($slugs, $locales),
            'slug_routes'  => array_keys($this->store->slugRoutes()),
        ]);
    }

    #[Route(path: '/slugs/new', name: 'slug_new', methods: ['GET', 'POST'])]
    public function slugNew(Request $request): Response
    {
        return $this->handleSlugForm($request, null, null);
    }

    #[Route(path: '/slugs/{route}/{slug}', name: 'slug_edit', methods: ['GET', 'POST'], requirements: ['route' => '[a-zA-Z0-9_.-]+', 'slug' => '[a-zA-Z0-9_.-]+'])]
    public function slugEdit(Request $request, string $route, string $slug): Response
    {
        $slugs = $this->store->slugs();
        if (!isset($slugs[$route][$slug])) {
            throw $this->createNotFoundException(sprintf('Slug "%s/%s" not found.', $route, $slug));
        }

        return $this->handleSlugForm($request, $route, $slug);
    }

    #[Route(path: '/slugs/{route}/{slug}/delete', name: 'slug_delete', methods: ['POST'], requirements: ['route' => '[a-zA-Z0-9_.-]+', 'slug' => '[a-zA-Z0-9_.-]+'])]
    public function slugDelete(Request $request, string $route, string $slug): Response
    {
        if (!$this->deleteSubmitted($request, $this->generateUrl('admin_seo_slug_delete', ['route' => $route, 'slug' => $slug]))) {
            return $this->redirectToRoute('admin_seo_slugs');
        }
        $this->store->deleteSlug($route, $slug);
        $this->addFlash('success', sprintf('Slug "%s/%s" deleted.', $route, $slug));

        return $this->redirectToRoute('admin_seo_slugs');
    }

    private function handlePageForm(Request $request, ?string $existingRoute): Response
    {
        $root    = $this->store->root();
        $locales = $this->localeList($root['locales'] ?? null);
        $pages   = $this->store->pages();
        $data    = $existingRoute !== null ? ($pages[$existingRoute] ?? []) : [];

        $form = $this->createForm(PageFormType::class, [
            'route'            => $existingRoute,
            'title'            => $data['title'] ?? null,
            'path'             => $data['path'] ?? null,
            'description'      => $data['description'] ?? null,
            'sitemap_priority' => (float) ($data['sitemap_priority'] ?? 0.8),
            'in_sitemap'       => (bool) ($data['in_sitemap'] ?? true),
            'locales'          => $this->localeData($data, $locales),
        ], ['locales' => $locales, 'is_new' => $existingRoute === null]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $submitted */
            $submitted = $form->getData();
            $route     = $existingRoute ?? $this->text($submitted['route'] ?? null) ?? '';
            if ($route === '') {
                $this->addFlash('error', 'Route name is required.');
            } elseif ($existingRoute === null && isset($pages[$route])) {
                $this->addFlash('error', sprintf('Page "%s" already exists.', $route));
            } else {
                $this->store->savePage($route, $this->pageFromData($submitted, $locales));
                $this->addFlash('success', sprintf('Page "%s" saved.', $route));

                return $this->redirectToRoute('admin_seo_page_edit', ['route' => $route]);
            }
        }

        return $this->render('admin/seo/page_form.html.twig', [
            'form'      => $form->createView(),
            'route'     => $existingRoute,
            'locales'   => $locales,
            'is_new'    => $existingRoute === null,
            'page_urls' => $existingRoute !== null
                ? ($this->buildPageUrls([$existingRoute => $data], $locales)[$existingRoute] ?? [])
                : [],
        ]);
    }

    private function handleSlugRouteForm(Request $request, ?string $existingRoute): Response
    {
        $root    = $this->store->root();
        $locales = $this->localeList($root['locales'] ?? null);
        $routes  = $this->store->slugRoutes();
        $data    = $existingRoute !== null ? ($routes[$existingRoute] ?? []) : [];

        $form = $this->createForm(SlugRouteFormType::class, [
            'route'                => $existingRoute,
            'slug_parameter'       => $data['slug_parameter'] ?? 'slug',
            'sitemap_priority'     => (float) ($data['sitemap_priority'] ?? 0.6),
            'path_pattern'         => $data['path_pattern'] ?? null,
            'title_template'       => $data['title_template'] ?? null,
            'description_template' => $data['description_template'] ?? null,
            'in_sitemap'           => (bool) ($data['in_sitemap'] ?? true),
            'locales'              => $this->localeData($data, $locales),
        ], ['locales' => $locales, 'is_new' => $existingRoute === null]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $submitted */
            $submitted = $form->getData();
            $route     = $existingRoute ?? $this->text($submitted['route'] ?? null) ?? '';
            if ($route === '') {
                $this->addFlash('error', 'Route name is required.');
            } elseif ($existingRoute === null && isset($routes[$route])) {
                $this->addFlash('error', sprintf('Slug route "%s" already exists.', $route));
            } else {
                $this->store->saveSlugRoute($route, $this->slugRouteFromData($submitted, $locales));
                $this->addFlash('success', sprintf('Slug route "%s" saved.', $route));

                return $this->redirectToRoute('admin_seo_slug_route_edit', ['route' => $route]);
            }
        }

        return $this->render('admin/seo/slug_route_form.html.twig', [
            'form'    => $form->createView(),
            'route'   => $existingRoute,
            'locales' => $locales,
            'is_new'  => $existingRoute === null,
        ]);
    }

    private function handleSlugForm(Request $request, ?string $existingRoute, ?string $existingSlug): Response
    {
        $root       = $this->store->root();
        $locales    = $this->localeList($root['locales'] ?? null);
        $slugs      = $this->store->slugs();
        $slugRoutes = array_values(array_filter(array_keys($this->store->slugRoutes()), is_string(...)));
        $data       = ($existingRoute !== null && $existingSlug !== null)
            ? ($slugs[$existingRoute][$existingSlug] ?? [])
            : [];
        $isNew = $existingSlug === null;

        $form = $this->createForm(SlugFormType::class, [
            'route'       => $existingRoute,
            'slug'        => $existingSlug,
            'title'       => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'in_sitemap'  => (bool) ($data['in_sitemap'] ?? true),
            'noindex'     => (bool) ($data['noindex'] ?? false),
            'locales'     => $this->localeData($data, $locales),
        ], ['locales' => $locales, 'is_new' => $isNew, 'slug_routes' => $slugRoutes]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $submitted */
            $submitted = $form->getData();
            $route     = $isNew ? ($this->text($submitted['route'] ?? null) ?? '') : (string) $existingRoute;
            $slug      = $isNew ? ($this->text($submitted['slug'] ?? null) ?? '') : $existingSlug;
            if ($route === '' || $slug === '') {
                $this->addFlash('error', 'Route and slug are required.');
            } elseif ($isNew && isset($slugs[$route][$slug])) {
                $this->addFlash('error', sprintf('Slug "%s/%s" already exists.', $route, $slug));
            } else {
                $this->store->saveSlug($route, $slug, $this->slugFromData($submitted, $locales));
                $this->addFlash('success', sprintf('Slug "%s/%s" saved.', $route, $slug));

                return $this->redirectToRoute('admin_seo_slug_edit', ['route' => $route, 'slug' => $slug]);
            }
        }

        $urls = [];
        if ($existingRoute !== null && $existingSlug !== null) {
            $urls = $this->buildSlugUrls([$existingRoute => [$existingSlug => $data]], $locales)[$existingRoute][$existingSlug] ?? [];
        }

        return $this->render('admin/seo/slug_form.html.twig', [
            'form'      => $form->createView(),
            'route'     => $existingRoute,
            'slug'      => $existingSlug,
            'locales'   => $locales,
            'is_new'    => $isNew,
            'slug_urls' => $urls,
        ]);
    }

    /**
     * POST-only delete with a Symfony Form CSRF token (REQ-SEC-005 / REQ-TWIG-005 in the demo).
     */
    private function deleteForm(string $action): FormInterface
    {
        return $this->container->get('form.factory')
            ->createNamedBuilder('delete', FormType::class, null, ['action' => $action])
            ->getForm();
    }

    private function deleteSubmitted(Request $request, string $action): bool
    {
        $form = $this->deleteForm($action);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return true;
        }
        $this->addFlash('error', 'Invalid delete request (CSRF token missing or expired).');

        return false;
    }

    /**
     * @return list<string>
     */
    private function localeList(mixed $locales): array
    {
        if (!is_array($locales)) {
            return ['en'];
        }

        return array_values(array_filter($locales, is_string(...)));
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $locales
     *
     * @return array<string, array<string, mixed>>
     */
    private function localeData(array $data, array $locales): array
    {
        $stored = is_array($data['locales'] ?? null) ? $data['locales'] : [];
        $out    = [];
        foreach ($locales as $locale) {
            $out[$locale] = is_array($stored[$locale] ?? null) ? $stored[$locale] : [];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $submitted
     * @param list<string> $locales
     * @param list<string> $fields
     *
     * @return array<string, array<string, string>>
     */
    private function submittedLocales(array $submitted, array $locales, array $fields): array
    {
        $byLocale = is_array($submitted['locales'] ?? null) ? $submitted['locales'] : [];
        $out      = [];
        foreach ($locales as $locale) {
            $row    = is_array($byLocale[$locale] ?? null) ? $byLocale[$locale] : [];
            $values = [];
            foreach ($fields as $field) {
                $value = $this->text($row[$field] ?? null);
                if ($value !== null) {
                    $values[$field] = $value;
                }
            }
            if ($values !== []) {
                $out[$locale] = $values;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $submitted
     * @param list<string> $locales
     *
     * @return array<string, mixed>
     */
    private function pageFromData(array $submitted, array $locales): array
    {
        $page = [
            'title'            => $this->text($submitted['title'] ?? null),
            'description'      => $this->text($submitted['description'] ?? null),
            'path'             => $this->text($submitted['path'] ?? null),
            'in_sitemap'       => $this->flag($submitted['in_sitemap'] ?? null),
            'sitemap_priority' => $this->number($submitted['sitemap_priority'] ?? null, 0.8),
        ];
        $localeData = $this->submittedLocales($submitted, $locales, ['title', 'path', 'description']);
        if ($localeData !== []) {
            $page['locales'] = $localeData;
        }

        return array_filter($page, static fn ($v) => $v !== null);
    }

    /**
     * @param array<string, mixed> $submitted
     * @param list<string> $locales
     *
     * @return array<string, mixed>
     */
    private function slugRouteFromData(array $submitted, array $locales): array
    {
        $cfg = [
            'slug_parameter'       => $this->text($submitted['slug_parameter'] ?? null) ?? 'slug',
            'title_template'       => $this->text($submitted['title_template'] ?? null),
            'description_template' => $this->text($submitted['description_template'] ?? null),
            'path_pattern'         => $this->text($submitted['path_pattern'] ?? null),
            'sitemap_priority'     => $this->number($submitted['sitemap_priority'] ?? null, 0.6),
            'in_sitemap'           => $this->flag($submitted['in_sitemap'] ?? null),
        ];
        $localeData = $this->submittedLocales($submitted, $locales, ['path_pattern', 'title_template']);
        if ($localeData !== []) {
            $cfg['locales'] = $localeData;
        }

        return array_filter($cfg, static fn ($v) => $v !== null);
    }

    /**
     * @param array<string, mixed> $submitted
     * @param list<string> $locales
     *
     * @return array<string, mixed>
     */
    private function slugFromData(array $submitted, array $locales): array
    {
        $cfg = [
            'title'       => $this->text($submitted['title'] ?? null),
            'description' => $this->text($submitted['description'] ?? null),
            'in_sitemap'  => $this->flag($submitted['in_sitemap'] ?? null),
            'noindex'     => $this->flag($submitted['noindex'] ?? null),
        ];
        $localeData = $this->submittedLocales($submitted, $locales, ['title', 'slug', 'path']);
        if ($localeData !== []) {
            $cfg['locales'] = $localeData;
        }

        return array_filter($cfg, static fn ($v) => $v !== null);
    }

    private function text(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function flag(mixed $value): bool
    {
        return is_bool($value) ? $value : false;
    }

    private function number(mixed $value, float $default): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @param array<string, array<string, mixed>> $pages
     * @param list<mixed|string> $locales
     *
     * @return array<string, array<string, string|null>>
     */
    private function buildPageUrls(array $pages, array $locales): array
    {
        $out = [];
        foreach ($pages as $route => $page) {
            if (!is_string($route)) {
                continue;
            }
            $out[$route] = [];
            foreach ($locales as $locale) {
                if (!is_string($locale)) {
                    continue;
                }
                $out[$route][$locale] = $this->paths()->pagePath($route, $locale);
            }
        }

        return $out;
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $slugs
     * @param list<mixed|string> $locales
     *
     * @return array<string, array<string, array<string, string|null>>>
     */
    private function buildSlugUrls(array $slugs, array $locales): array
    {
        $out = [];
        foreach ($slugs as $route => $bySlug) {
            if (!is_string($route) || !is_array($bySlug)) {
                continue;
            }
            $out[$route] = [];
            foreach ($bySlug as $slug => $cfg) {
                if (!is_string($slug)) {
                    continue;
                }
                $out[$route][$slug] = [];
                foreach ($locales as $locale) {
                    if (!is_string($locale)) {
                        continue;
                    }
                    $out[$route][$slug][$locale] = $this->paths()->slugPath($route, $locale, $slug);
                }
            }
        }

        return $out;
    }
}
