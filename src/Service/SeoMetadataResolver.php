<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Attribute\Seo;
use Nowo\SeoKitBundle\Model\SeoMetadata;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

use function array_key_exists;
use function is_array;
use function is_bool;
use function is_int;
use function is_scalar;
use function is_string;
use function strlen;

/**
 * Merges SEO layers: defaults → slug_routes → pages → slugs → attribute → runtime.
 */
final readonly class SeoMetadataResolver
{
    /**
     * @param array<string, mixed> $config
     * @param iterable<SeoDefaultsProviderInterface> $defaultsProviders
     */
    public function __construct(
        private array $config,
        private RequestStack $requestStack,
        private SeoRuntime $runtime,
        private SeoTemplateRenderer $templates,
        private SeoPathBuilderInterface $paths,
        private UrlGeneratorInterface $urlGenerator,
        #[TaggedIterator('nowo_seo_kit.defaults_provider')]
        private iterable $defaultsProviders = [],
    ) {
    }

    public function resolve(?Request $request = null): SeoMetadata
    {
        $request ??= $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request || !($this->config['enabled'] ?? true)) {
            return $this->emptyMetadata();
        }

        $route    = (string) $request->attributes->get('_route', '');
        $locale   = (string) ($request->attributes->get('_locale') ?? $request->getLocale() ?: ($this->config['default_locale'] ?? 'en'));
        $defaults = is_array($this->config['defaults'] ?? null) ? $this->config['defaults'] : [];
        $defaults = $this->mergeDefaultsProviders($defaults);

        if (($this->config['indexable'] ?? true) !== true && (!isset($defaults['robots']) || $defaults['robots'] === '')) {
            $defaults['robots'] = 'noindex,nofollow';
        }

        $slugParam    = 'slug';
        $slugRouteCfg = is_array($this->config['slug_routes'][$route] ?? null) ? $this->config['slug_routes'][$route] : null;
        if (is_array($slugRouteCfg) && isset($slugRouteCfg['slug_parameter']) && is_string($slugRouteCfg['slug_parameter'])) {
            $slugParam = $slugRouteCfg['slug_parameter'];
        }
        $slug = $request->attributes->get($slugParam);
        $slug = is_string($slug) ? $slug : null;
        if ($slug !== null) {
            $slug = $this->paths->resolveCanonicalSlug($route, $slug);
        }

        $merged = $defaults;
        // Document title wrapper ({title}{separator}{site_name}) is applied later — do not treat it as page title.
        unset($merged['title_template']);
        $source = 'defaults';

        if (is_array($slugRouteCfg)) {
            $merged     = $this->mergeLayer($merged, $slugRouteCfg);
            $source     = 'slug_routes';
            $localeSlug = $slugRouteCfg['locales'][$locale] ?? null;
            if (is_array($localeSlug)) {
                $merged = $this->mergeLayer($merged, $localeSlug);
            }
        }

        $pageCfg = is_array($this->config['pages'][$route] ?? null) ? $this->config['pages'][$route] : null;
        if (is_array($pageCfg)) {
            $merged     = $this->mergeLayer($merged, $pageCfg);
            $source     = 'pages';
            $pageLocale = $pageCfg['locales'][$locale] ?? null;
            if (is_array($pageLocale)) {
                $merged = $this->mergeLayer($merged, $pageLocale);
            }
        }

        if ($slug !== null && is_array($this->config['slugs'][$route][$slug] ?? null)) {
            $slugCfg = $this->config['slugs'][$route][$slug];
            $merged  = $this->mergeLayer($merged, $slugCfg);
            $source  = 'slugs';
            if (($slugCfg['noindex'] ?? false) === true) {
                $merged['robots'] = 'noindex,nofollow';
            }
            $slugLocale = $slugCfg['locales'][$locale] ?? null;
            if (is_array($slugLocale)) {
                $merged = $this->mergeLayer($merged, $slugLocale);
            }
        }

        $attr = $this->readSeoAttribute($request);
        if ($attr !== null) {
            $merged = $this->mergeLayer($merged, $attr);
            $source = 'attribute';
        }

        $runtimeOverrides = $this->runtime->getOverrides();
        if ($runtimeOverrides !== []) {
            $merged = $this->mergeLayer($merged, $runtimeOverrides);
            $source = 'runtime';
            if (($runtimeOverrides['noindex'] ?? false) === true) {
                $merged['robots'] = 'noindex,nofollow';
            }
        }

        $variables = array_merge(
            $this->scalarAttributes($request),
            $this->runtime->getVariables(),
            [
                'site_name' => (string) ($defaults['site_name'] ?? ''),
                'separator' => (string) ($defaults['title_separator'] ?? ' | '),
                'locale'    => $locale,
                'slug'      => $slug ?? '',
            ],
        );

        // Prefer an explicit title over a content title_template (slug/page layers).
        if (isset($merged['title']) && is_string($merged['title']) && $merged['title'] !== '') {
            $titleBase = $this->templates->render($merged['title'], $variables) ?? $merged['title'];
        } elseif (isset($merged['title_template']) && is_string($merged['title_template']) && $merged['title_template'] !== '') {
            $titleBase = $this->templates->render($merged['title_template'], $variables) ?? '';
        } else {
            $titleBase = (string) ($defaults['site_name'] ?? '');
        }

        $titleFinal = ($runtimeOverrides['title_final'] ?? false) === true
            || ($merged['title_final'] ?? false) === true;

        if ($titleFinal) {
            $title = $titleBase;
        } else {
            $title = $this->templates->render(
                (string) ($defaults['title_template'] ?? '{title}{separator}{site_name}'),
                array_merge($variables, ['title' => $titleBase]),
            ) ?? $titleBase;

            // Avoid trailing separator when site_name empty
            $separator = (string) ($defaults['title_separator'] ?? ' | ');
            $siteName  = (string) ($defaults['site_name'] ?? '');
            if ($siteName === '' && str_ends_with($title, $separator)) {
                $title = substr($title, 0, -strlen($separator));
            }
        }

        if (isset($merged['description']) && is_string($merged['description']) && $merged['description'] !== '') {
            $description = $this->templates->render($merged['description'], $variables) ?? $merged['description'];
        } elseif (isset($merged['description_template']) && is_string($merged['description_template']) && $merged['description_template'] !== '') {
            $description = $this->templates->render($merged['description_template'], $variables) ?? '';
        } else {
            $description = '';
        }

        $robots = is_string($merged['robots'] ?? null) && $merged['robots'] !== ''
            ? $merged['robots']
            : 'index,follow';

        $canonicalPath    = $this->resolveCanonicalPath($request, $route, $locale, $slug, $merged);
        $canonicalEnabled = (bool) ($defaults['canonical_enabled'] ?? true);
        $canonical        = $canonicalEnabled
            ? $this->paths->absoluteUrl($request, $canonicalPath)
            : null;

        $alternates = [];
        if (isset($runtimeOverrides['alternates']) && is_array($runtimeOverrides['alternates'])) {
            $alternates = $this->normalizeAlternates($runtimeOverrides['alternates']);
        } elseif (($defaults['hreflang_enabled'] ?? true) === true) {
            $alternates = $this->buildAlternates($request, $route, $slug, $locale);
        }

        $og      = is_array($merged['open_graph'] ?? null) ? $merged['open_graph'] : [];
        $tw      = is_array($merged['twitter'] ?? null) ? $merged['twitter'] : [];
        $ogImage = is_string($merged['og_image'] ?? null) ? $merged['og_image'] : ($og['image'] ?? null);

        $localeAlternates = [];
        if (isset($og['locale_alternates']) && is_array($og['locale_alternates'])) {
            foreach ($og['locale_alternates'] as $altLocale) {
                if (is_string($altLocale) && $altLocale !== '') {
                    $localeAlternates[] = str_replace('-', '_', $altLocale);
                }
            }
        }

        $openGraph = [
            'enabled'           => (bool) ($og['enabled'] ?? true),
            'type'              => (string) ($og['type'] ?? 'website'),
            'title'             => is_string($og['title'] ?? null) ? $og['title'] : $title,
            'description'       => is_string($og['description'] ?? null) ? $og['description'] : $description,
            'image'             => is_string($ogImage) ? $this->paths->absoluteUrl($request, $ogImage) : null,
            'image_width'       => is_int($og['image_width'] ?? null) ? $og['image_width'] : null,
            'image_height'      => is_int($og['image_height'] ?? null) ? $og['image_height'] : null,
            'image_alt'         => is_string($og['image_alt'] ?? null) ? $og['image_alt'] : null,
            'url'               => $canonical,
            'site_name'         => is_string($og['site_name'] ?? null) ? $og['site_name'] : ($defaults['site_name'] ?? null),
            'locale'            => is_string($og['locale'] ?? null) ? $og['locale'] : str_replace('-', '_', $locale),
            'locale_alternates' => $localeAlternates,
        ];

        $twitter = [
            'enabled'     => (bool) ($tw['enabled'] ?? true),
            'card'        => (string) ($tw['card'] ?? 'summary_large_image'),
            'title'       => is_string($tw['title'] ?? null) ? $tw['title'] : $title,
            'description' => is_string($tw['description'] ?? null) ? $tw['description'] : $description,
            'image'       => is_string($tw['image'] ?? null)
                ? $this->paths->absoluteUrl($request, $tw['image'])
                : $openGraph['image'],
            'site'    => is_string($tw['site'] ?? null) ? $tw['site'] : null,
            'creator' => is_string($tw['creator'] ?? null) ? $tw['creator'] : null,
        ];

        $jsonLd = $this->buildJsonLd($defaults, $merged, $request, $canonical, $title, $description);

        $verificationCfg = is_array($defaults['verification'] ?? null) ? $defaults['verification'] : [];
        if (isset($merged['verification']) && is_array($merged['verification'])) {
            $verificationCfg = array_replace($verificationCfg, $merged['verification']);
        }
        $verification = [
            'google' => is_string($verificationCfg['google'] ?? null) && $verificationCfg['google'] !== ''
                ? $verificationCfg['google']
                : null,
            'bing' => is_string($verificationCfg['bing'] ?? null) && $verificationCfg['bing'] !== ''
                ? $verificationCfg['bing']
                : null,
        ];

        return new SeoMetadata(
            title: $title,
            description: $description,
            robots: $robots,
            canonical: $canonical,
            alternates: $alternates,
            openGraph: $openGraph,
            twitter: $twitter,
            jsonLd: $jsonLd,
            keywords: is_string($merged['keywords'] ?? null) ? $merged['keywords'] : null,
            author: is_string($merged['author'] ?? null) ? $merged['author'] : null,
            verification: $verification,
            source: $source,
        );
    }

    /**
     * @param array<string, mixed> $defaults
     *
     * @return array<string, mixed>
     */
    private function mergeDefaultsProviders(array $defaults): array
    {
        foreach ($this->defaultsProviders as $provider) {
            $layer    = $provider->getDefaults();
            $defaults = $this->mergeLayer($defaults, $layer);
            if (isset($layer['verification']) && is_array($layer['verification'])) {
                $defaults['verification'] = array_replace(
                    is_array($defaults['verification'] ?? null) ? $defaults['verification'] : [],
                    $layer['verification'],
                );
            }
            if (isset($layer['json_ld']) && is_array($layer['json_ld'])) {
                $defaults['json_ld'] = array_replace(
                    is_array($defaults['json_ld'] ?? null) ? $defaults['json_ld'] : [],
                    $layer['json_ld'],
                );
            }
            if (array_key_exists('indexable', $layer) && is_bool($layer['indexable'])) {
                // Hosts may signal indexability via defaults; mirror into config-like robots.
                if ($layer['indexable'] === false && (!isset($defaults['robots']) || $defaults['robots'] === '')) {
                    $defaults['robots'] = 'noindex,nofollow';
                }
            }
        }

        return $defaults;
    }

    /**
     * @param array<string, mixed>|list<mixed> $raw
     *
     * @return list<array{locale: string, url: string, hreflang: string}>
     */
    private function normalizeAlternates(array $raw): array
    {
        $out = [];
        foreach ($raw as $key => $value) {
            if (is_array($value) && isset($value['url'], $value['hreflang']) && is_string($value['url']) && is_string($value['hreflang'])) {
                $out[] = [
                    'locale'   => is_string($value['locale'] ?? null) ? $value['locale'] : $value['hreflang'],
                    'url'      => $value['url'],
                    'hreflang' => $value['hreflang'],
                ];
                continue;
            }
            if (is_string($key) && is_string($value)) {
                $out[] = [
                    'locale'   => $key,
                    'url'      => $value,
                    'hreflang' => $key,
                ];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $layer
     *
     * @return array<string, mixed>
     */
    private function mergeLayer(array $base, array $layer): array
    {
        foreach (['title', 'description', 'robots', 'canonical', 'keywords', 'author', 'title_template', 'description_template', 'og_image', 'title_final', 'site_name', 'title_separator'] as $key) {
            if (array_key_exists($key, $layer) && $layer[$key] !== '' && $layer[$key] !== null) {
                $base[$key] = $layer[$key];
            }
        }
        foreach (['open_graph', 'twitter', 'json_ld', 'verification'] as $nested) {
            if (isset($layer[$nested]) && is_array($layer[$nested])) {
                $base[$nested] = array_replace(is_array($base[$nested] ?? null) ? $base[$nested] : [], $layer[$nested]);
            }
        }
        if (isset($layer['alternates']) && is_array($layer['alternates'])) {
            $base['alternates'] = $layer['alternates'];
        }

        return $base;
    }

    /**
     * @return array<string, string>
     */
    private function scalarAttributes(Request $request): array
    {
        $out = [];
        foreach ($request->attributes->all() as $key => $value) {
            if (is_scalar($value)) {
                $out[(string) $key] = (string) $value;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $merged
     */
    private function resolveCanonicalPath(Request $request, string $route, string $locale, ?string $slug, array $merged): string
    {
        if (isset($merged['canonical']) && is_string($merged['canonical']) && $merged['canonical'] !== '') {
            return $merged['canonical'];
        }

        if ($slug !== null) {
            $path = $this->paths->slugPath($route, $locale, $slug);
            if ($path !== null) {
                return $path;
            }
        }

        $pagePath = $this->paths->pagePath($route, $locale);
        if ($pagePath !== null) {
            return $pagePath;
        }

        return $request->getPathInfo();
    }

    /**
     * @return list<array{locale: string, url: string, hreflang: string}>
     */
    private function buildAlternates(Request $request, string $route, ?string $slug, string $currentLocale): array
    {
        $locales       = is_array($this->config['locales'] ?? null) ? $this->config['locales'] : [$currentLocale];
        $defaultLocale = (string) ($this->config['default_locale'] ?? 'en');
        $alternates    = [];

        foreach ($locales as $locale) {
            if (!is_string($locale)) {
                continue;
            }
            $path = null;
            if ($slug !== null) {
                $path = $this->paths->slugPath($route, $locale, $slug);
            }
            $path ??= $this->paths->pagePath($route, $locale);
            if ($path === null && $locale === $currentLocale) {
                $path = $request->getPathInfo();
            }
            if ($path === null) {
                // Try generating route with _locale
                try {
                    $params = array_filter(
                        $request->attributes->get('_route_params', []),
                        is_scalar(...),
                    );
                    $params['_locale'] = $locale;
                    $path              = $this->urlGenerator->generate($route, $params);
                } catch (Throwable) {
                    continue;
                }
            }
            $alternates[] = [
                'locale'   => $locale,
                'url'      => $this->paths->absoluteUrl($request, $path),
                'hreflang' => str_replace('_', '-', $locale),
            ];
        }

        $defaults = is_array($this->config['defaults'] ?? null) ? $this->config['defaults'] : [];
        if (($defaults['x_default_hreflang'] ?? true) === true) {
            foreach ($alternates as $alt) {
                if ($alt['locale'] === $defaultLocale) {
                    $alternates[] = [
                        'locale'   => 'x-default',
                        'url'      => $alt['url'],
                        'hreflang' => 'x-default',
                    ];
                    break;
                }
            }
        }

        return $alternates;
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $merged
     *
     * @return array{enabled: bool, graph: list<array<string, mixed>>, document: array<string, mixed>|null, json: ?string}
     */
    private function buildJsonLd(array $defaults, array $merged, Request $request, ?string $canonical, string $title, string $description): array
    {
        $cfg = is_array($defaults['json_ld'] ?? null) ? $defaults['json_ld'] : [];
        if (isset($merged['json_ld']) && is_array($merged['json_ld'])) {
            $cfg = array_replace($cfg, $merged['json_ld']);
        }

        $enabled = (bool) ($cfg['enabled'] ?? true);

        if (isset($cfg['json']) && is_string($cfg['json']) && $cfg['json'] !== '') {
            return ['enabled' => $enabled, 'graph' => [], 'document' => null, 'json' => $cfg['json']];
        }

        if (isset($cfg['document']) && is_array($cfg['document'])) {
            return ['enabled' => $enabled, 'graph' => [], 'document' => $cfg['document'], 'json' => null];
        }

        if (isset($cfg['graph']) && is_array($cfg['graph']) && $cfg['graph'] !== []) {
            /** @var list<array<string, mixed>> $graph */
            $graph = array_values(array_filter($cfg['graph'], is_array(...)));

            return ['enabled' => $enabled, 'graph' => $graph, 'document' => null, 'json' => null];
        }

        $graph = [];

        $org = is_array($cfg['organization'] ?? null) ? $cfg['organization'] : [];
        if ($org !== []) {
            $graph[] = array_replace(['@type' => 'Organization'], $org);
        }

        $graph[] = [
            '@type'       => 'WebPage',
            'name'        => $title,
            'description' => $description,
            'url'         => $canonical ?? $this->paths->absoluteUrl($request, $request->getPathInfo()),
        ];

        $extra = is_array($cfg['extra'] ?? null) ? $cfg['extra'] : [];
        foreach ($extra as $item) {
            if (is_array($item)) {
                $graph[] = $item;
            }
        }

        return ['enabled' => $enabled, 'graph' => $graph, 'document' => null, 'json' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readSeoAttribute(Request $request): ?array
    {
        $controller = $request->attributes->get('_controller');
        if (!is_string($controller) || !str_contains($controller, '::')) {
            return null;
        }

        [$class, $method] = explode('::', $controller, 2);
        if (!class_exists($class)) {
            return null;
        }

        $refClass = new ReflectionClass($class);
        $attrs    = $refClass->getAttributes(Seo::class);
        if ($refClass->hasMethod($method)) {
            $refMethod = new ReflectionMethod($class, $method);
            $attrs     = array_merge($attrs, $refMethod->getAttributes(Seo::class));
        }

        if ($attrs === []) {
            return null;
        }

        /** @var Seo $seo */
        $seo = $attrs[array_key_last($attrs)]->newInstance();
        $out = array_filter([
            'title'       => $seo->title,
            'description' => $seo->description,
            'robots'      => $seo->robots,
            'canonical'   => $seo->canonical,
            'keywords'    => $seo->keywords,
            'author'      => $seo->author,
        ], static fn (?string $v): bool => $v !== null);

        if ($seo->noindex) {
            $out['robots'] = 'noindex,nofollow';
        }
        if (is_array($seo->openGraph)) {
            $out['open_graph'] = $seo->openGraph;
        }
        if (is_array($seo->twitter)) {
            $out['twitter'] = $seo->twitter;
        }

        return $out;
    }

    private function emptyMetadata(): SeoMetadata
    {
        return new SeoMetadata(
            title: '',
            description: '',
            robots: 'noindex,nofollow',
            canonical: null,
            alternates: [],
            openGraph: [
                'enabled'           => false,
                'type'              => 'website',
                'title'             => null,
                'description'       => null,
                'image'             => null,
                'image_width'       => null,
                'image_height'      => null,
                'image_alt'         => null,
                'url'               => null,
                'site_name'         => null,
                'locale'            => null,
                'locale_alternates' => [],
            ],
            twitter: ['enabled' => false, 'card' => 'summary', 'title' => null, 'description' => null, 'image' => null, 'site' => null, 'creator' => null],
            jsonLd: ['enabled' => false, 'graph' => [], 'document' => null, 'json' => null],
            verification: ['google' => null, 'bing' => null],
            source: 'disabled',
        );
    }
}
