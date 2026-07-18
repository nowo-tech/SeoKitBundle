<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SeoConfigStore;
use Nowo\SeoKitBundle\Attribute\Seo;
use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function is_array;
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

        return $this->render('admin/seo/pages.html.twig', [
            'pages'     => $pages,
            'locales'   => $locales,
            'page_urls' => $this->buildPageUrls($pages, $locales),
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
    public function pageDelete(string $route): Response
    {
        $this->store->deletePage($route);
        $this->addFlash('success', sprintf('Page "%s" deleted.', $route));

        return $this->redirectToRoute('admin_seo_pages');
    }

    #[Route(path: '/slug-routes', name: 'slug_routes', methods: ['GET'])]
    public function slugRoutes(): Response
    {
        return $this->render('admin/seo/slug_routes.html.twig', [
            'slug_routes' => $this->store->slugRoutes(),
            'locales'     => $this->store->root()['locales'] ?? ['en'],
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
    public function slugRouteDelete(string $route): Response
    {
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

        return $this->render('admin/seo/slugs.html.twig', [
            'slugs'       => $slugs,
            'locales'     => $locales,
            'slug_urls'   => $this->buildSlugUrls($slugs, $locales),
            'slug_routes' => array_keys($this->store->slugRoutes()),
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
    public function slugDelete(string $route, string $slug): Response
    {
        $this->store->deleteSlug($route, $slug);
        $this->addFlash('success', sprintf('Slug "%s/%s" deleted.', $route, $slug));

        return $this->redirectToRoute('admin_seo_slugs');
    }

    private function handlePageForm(Request $request, ?string $existingRoute): Response
    {
        $root    = $this->store->root();
        $locales = is_array($root['locales'] ?? null) ? $root['locales'] : ['en'];
        $pages   = $this->store->pages();
        $data    = $existingRoute !== null ? ($pages[$existingRoute] ?? []) : [];

        if ($request->isMethod('POST')) {
            $route = trim((string) $request->request->get('route', $existingRoute ?? ''));
            if ($route === '') {
                $this->addFlash('error', 'Route name is required.');
            } elseif ($existingRoute === null && isset($pages[$route])) {
                $this->addFlash('error', sprintf('Page "%s" already exists.', $route));
            } else {
                $page = $this->pageFromRequest($request, $locales);
                if ($existingRoute !== null && $existingRoute !== $route) {
                    $this->store->deletePage($existingRoute);
                }
                $this->store->savePage($route, $page);
                $this->addFlash('success', sprintf('Page "%s" saved.', $route));

                return $this->redirectToRoute('admin_seo_page_edit', ['route' => $route]);
            }
        }

        return $this->render('admin/seo/page_form.html.twig', [
            'route'     => $existingRoute,
            'data'      => $data,
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
        $locales = is_array($root['locales'] ?? null) ? $root['locales'] : ['en'];
        $routes  = $this->store->slugRoutes();
        $data    = $existingRoute !== null ? ($routes[$existingRoute] ?? []) : [];

        if ($request->isMethod('POST')) {
            $route = trim((string) $request->request->get('route', $existingRoute ?? ''));
            if ($route === '') {
                $this->addFlash('error', 'Route name is required.');
            } elseif ($existingRoute === null && isset($routes[$route])) {
                $this->addFlash('error', sprintf('Slug route "%s" already exists.', $route));
            } else {
                $slugRoute = $this->slugRouteFromRequest($request, $locales);
                if ($existingRoute !== null && $existingRoute !== $route) {
                    $this->store->deleteSlugRoute($existingRoute);
                }
                $this->store->saveSlugRoute($route, $slugRoute);
                $this->addFlash('success', sprintf('Slug route "%s" saved.', $route));

                return $this->redirectToRoute('admin_seo_slug_route_edit', ['route' => $route]);
            }
        }

        return $this->render('admin/seo/slug_route_form.html.twig', [
            'route'   => $existingRoute,
            'data'    => $data,
            'locales' => $locales,
            'is_new'  => $existingRoute === null,
        ]);
    }

    private function handleSlugForm(Request $request, ?string $existingRoute, ?string $existingSlug): Response
    {
        $root       = $this->store->root();
        $locales    = is_array($root['locales'] ?? null) ? $root['locales'] : ['en'];
        $slugs      = $this->store->slugs();
        $slugRoutes = array_keys($this->store->slugRoutes());
        $data       = ($existingRoute !== null && $existingSlug !== null)
            ? ($slugs[$existingRoute][$existingSlug] ?? [])
            : [];

        if ($request->isMethod('POST')) {
            $route = trim((string) $request->request->get('route', $existingRoute ?? ''));
            $slug  = trim((string) $request->request->get('slug', $existingSlug ?? ''));
            if ($route === '' || $slug === '') {
                $this->addFlash('error', 'Route and slug are required.');
            } elseif ($existingSlug === null && isset($slugs[$route][$slug])) {
                $this->addFlash('error', sprintf('Slug "%s/%s" already exists.', $route, $slug));
            } else {
                $slugCfg = $this->slugFromRequest($request, $locales);
                if ($existingRoute !== null && $existingSlug !== null
                    && ($existingRoute !== $route || $existingSlug !== $slug)
                ) {
                    $this->store->deleteSlug($existingRoute, $existingSlug);
                }
                $this->store->saveSlug($route, $slug, $slugCfg);
                $this->addFlash('success', sprintf('Slug "%s/%s" saved.', $route, $slug));

                return $this->redirectToRoute('admin_seo_slug_edit', ['route' => $route, 'slug' => $slug]);
            }
        }

        $urls = [];
        if ($existingRoute !== null && $existingSlug !== null) {
            $urls = $this->buildSlugUrls([$existingRoute => [$existingSlug => $data]], $locales)[$existingRoute][$existingSlug] ?? [];
        }

        return $this->render('admin/seo/slug_form.html.twig', [
            'route'       => $existingRoute,
            'slug'        => $existingSlug,
            'data'        => $data,
            'locales'     => $locales,
            'slug_routes' => $slugRoutes,
            'is_new'      => $existingSlug === null,
            'slug_urls'   => $urls,
        ]);
    }

    /**
     * @param list<mixed|string> $locales
     *
     * @return array<string, mixed>
     */
    private function pageFromRequest(Request $request, array $locales): array
    {
        $page = [
            'title'            => $this->nullIfEmpty($request->request->getString('title')),
            'description'      => $this->nullIfEmpty($request->request->getString('description')),
            'path'             => $this->nullIfEmpty($request->request->getString('path')),
            'in_sitemap'       => $request->request->getBoolean('in_sitemap'),
            'sitemap_priority' => (float) $request->request->get('sitemap_priority', 0.8),
        ];

        $localeData = [];
        foreach ($locales as $locale) {
            if (!is_string($locale)) {
                continue;
            }
            $title = $this->nullIfEmpty($request->request->getString('locale_' . $locale . '_title'));
            $path  = $this->nullIfEmpty($request->request->getString('locale_' . $locale . '_path'));
            $desc  = $this->nullIfEmpty($request->request->getString('locale_' . $locale . '_description'));
            if ($title === null && $path === null && $desc === null) {
                continue;
            }
            $localeData[$locale] = array_filter([
                'title'       => $title,
                'path'        => $path,
                'description' => $desc,
            ], static fn ($v) => $v !== null);
        }
        if ($localeData !== []) {
            $page['locales'] = $localeData;
        }

        return array_filter($page, static fn ($v) => $v !== null);
    }

    /**
     * @param list<mixed|string> $locales
     *
     * @return array<string, mixed>
     */
    private function slugRouteFromRequest(Request $request, array $locales): array
    {
        $cfg = [
            'slug_parameter'       => $this->nullIfEmpty($request->request->getString('slug_parameter')) ?? 'slug',
            'title_template'       => $this->nullIfEmpty($request->request->getString('title_template')),
            'description_template' => $this->nullIfEmpty($request->request->getString('description_template')),
            'path_pattern'         => $this->nullIfEmpty($request->request->getString('path_pattern')),
            'sitemap_priority'     => (float) $request->request->get('sitemap_priority', 0.6),
            'in_sitemap'           => $request->request->getBoolean('in_sitemap', true),
        ];

        $localeData = [];
        foreach ($locales as $locale) {
            if (!is_string($locale)) {
                continue;
            }
            $pattern = $this->nullIfEmpty($request->request->getString('locale_' . $locale . '_path_pattern'));
            $title   = $this->nullIfEmpty($request->request->getString('locale_' . $locale . '_title_template'));
            if ($pattern === null && $title === null) {
                continue;
            }
            $localeData[$locale] = array_filter([
                'path_pattern'   => $pattern,
                'title_template' => $title,
            ], static fn ($v) => $v !== null);
        }
        if ($localeData !== []) {
            $cfg['locales'] = $localeData;
        }

        return array_filter($cfg, static fn ($v) => $v !== null);
    }

    /**
     * @param list<mixed|string> $locales
     *
     * @return array<string, mixed>
     */
    private function slugFromRequest(Request $request, array $locales): array
    {
        $cfg = [
            'title'       => $this->nullIfEmpty($request->request->getString('title')),
            'description' => $this->nullIfEmpty($request->request->getString('description')),
            'in_sitemap'  => $request->request->getBoolean('in_sitemap', true),
            'noindex'     => $request->request->getBoolean('noindex'),
        ];

        $localeData = [];
        foreach ($locales as $locale) {
            if (!is_string($locale)) {
                continue;
            }
            $title = $this->nullIfEmpty($request->request->getString('locale_' . $locale . '_title'));
            $slug  = $this->nullIfEmpty($request->request->getString('locale_' . $locale . '_slug'));
            $path  = $this->nullIfEmpty($request->request->getString('locale_' . $locale . '_path'));
            if ($title === null && $slug === null && $path === null) {
                continue;
            }
            $localeData[$locale] = array_filter([
                'title' => $title,
                'slug'  => $slug,
                'path'  => $path,
            ], static fn ($v) => $v !== null);
        }
        if ($localeData !== []) {
            $cfg['locales'] = $localeData;
        }

        return array_filter($cfg, static fn ($v) => $v !== null);
    }

    private function nullIfEmpty(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
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
