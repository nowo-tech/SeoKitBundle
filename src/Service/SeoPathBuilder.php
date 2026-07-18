<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Symfony\Component\HttpFoundation\Request;

use function is_array;
use function is_string;

/**
 * Builds absolute/relative SEO paths and locale alternates.
 */
final readonly class SeoPathBuilder
{
    /**
     * @param array<string, mixed> $config Full processed nowo_seo_kit config
     */
    public function __construct(
        private array $config,
    ) {
    }

    public function absoluteUrl(Request $request, string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base = $this->config['base_url'] ?? null;
        if (is_string($base) && $base !== '') {
            return rtrim($base, '/') . '/' . ltrim($path, '/');
        }

        return $request->getSchemeAndHttpHost() . '/' . ltrim($path, '/');
    }

    /**
     * Resolve public path for a static page route + locale.
     */
    public function pagePath(string $route, string $locale, ?string $fallbackPath = null): ?string
    {
        $page = $this->config['pages'][$route] ?? null;
        if (!is_array($page)) {
            return $fallbackPath;
        }

        $localeCfg = $page['locales'][$locale] ?? null;
        if (is_array($localeCfg) && isset($localeCfg['path']) && is_string($localeCfg['path']) && $localeCfg['path'] !== '') {
            return $localeCfg['path'];
        }

        if (isset($page['path']) && is_string($page['path']) && $page['path'] !== '' && $locale === ($this->config['default_locale'] ?? 'en')) {
            return $page['path'];
        }

        return $fallbackPath;
    }

    /**
     * Map a request slug (canonical or translated) to the configured slug key.
     */
    public function resolveCanonicalSlug(string $route, string $slug): string
    {
        $slugs = $this->config['slugs'][$route] ?? null;
        if (!is_array($slugs)) {
            return $slug;
        }

        if (isset($slugs[$slug]) && is_array($slugs[$slug])) {
            return $slug;
        }

        foreach ($slugs as $canonical => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            $locales = $cfg['locales'] ?? null;
            if (!is_array($locales)) {
                continue;
            }
            foreach ($locales as $localeCfg) {
                if (is_array($localeCfg) && ($localeCfg['slug'] ?? null) === $slug) {
                    return (string) $canonical;
                }
            }
        }

        return $slug;
    }

    /**
     * Build path from slug_routes path_pattern for locale + slug.
     *
     * `$slug` may be the canonical config key or a translated locale slug.
     */
    public function slugPath(string $route, string $locale, string $slug): ?string
    {
        $slugRoute = $this->config['slug_routes'][$route] ?? null;
        if (!is_array($slugRoute)) {
            return null;
        }

        $canonical = $this->resolveCanonicalSlug($route, $slug);

        $pattern   = null;
        $localeCfg = $slugRoute['locales'][$locale] ?? null;
        if (is_array($localeCfg) && isset($localeCfg['path_pattern']) && is_string($localeCfg['path_pattern'])) {
            $pattern = $localeCfg['path_pattern'];
        } elseif (isset($slugRoute['path_pattern']) && is_string($slugRoute['path_pattern'])) {
            $pattern = $slugRoute['path_pattern'];
        }

        if ($pattern === null || $pattern === '') {
            // Specific slug path override
            $specific = $this->config['slugs'][$route][$canonical]['locales'][$locale]['path']
                ?? $this->config['slugs'][$route][$canonical]['path']
                ?? null;
            if (is_string($specific) && $specific !== '') {
                return $specific;
            }

            return null;
        }

        $translatedSlug = $this->config['slugs'][$route][$canonical]['locales'][$locale]['slug'] ?? $canonical;

        return strtr($pattern, [
            '{slug}'   => (string) $translatedSlug,
            '{locale}' => $locale,
        ]);
    }
}
