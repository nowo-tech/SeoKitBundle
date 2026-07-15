<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Builds absolute/relative SEO paths and locale alternates.
 */
final class SeoPathBuilder
{
    /**
     * @param array<string, mixed> $config Full processed nowo_seo_kit config
     */
    public function __construct(
        private readonly array $config,
    ) {
    }

    public function absoluteUrl(Request $request, string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base = $this->config['base_url'] ?? null;
        if (is_string($base) && $base !== '') {
            return rtrim($base, '/').'/'.ltrim($path, '/');
        }

        return $request->getSchemeAndHttpHost().'/'.ltrim($path, '/');
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
     * Build path from slug_routes path_pattern for locale + slug.
     */
    public function slugPath(string $route, string $locale, string $slug): ?string
    {
        $slugRoute = $this->config['slug_routes'][$route] ?? null;
        if (!is_array($slugRoute)) {
            return null;
        }

        $pattern = null;
        $localeCfg = $slugRoute['locales'][$locale] ?? null;
        if (is_array($localeCfg) && isset($localeCfg['path_pattern']) && is_string($localeCfg['path_pattern'])) {
            $pattern = $localeCfg['path_pattern'];
        } elseif (isset($slugRoute['path_pattern']) && is_string($slugRoute['path_pattern'])) {
            $pattern = $slugRoute['path_pattern'];
        }

        if ($pattern === null || $pattern === '') {
            // Specific slug path override
            $specific = $this->config['slugs'][$route][$slug]['locales'][$locale]['path']
                ?? $this->config['slugs'][$route][$slug]['path']
                ?? null;
            if (is_string($specific) && $specific !== '') {
                return $specific;
            }

            return null;
        }

        $translatedSlug = $this->config['slugs'][$route][$slug]['locales'][$locale]['slug'] ?? $slug;

        return strtr($pattern, [
            '{slug}' => (string) $translatedSlug,
            '{locale}' => $locale,
        ]);
    }
}
