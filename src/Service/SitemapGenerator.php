<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Symfony\Component\HttpFoundation\Request;

use function is_array;
use function is_string;

use const ENT_XML1;

/**
 * Builds sitemap.xml entries from configured static pages and explicit slugs.
 */
final readonly class SitemapGenerator
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $config,
        private SeoPathBuilder $paths,
    ) {
    }

    /**
     * @return list<array{loc: string, changefreq: string, priority: string}>
     */
    public function entries(Request $request): array
    {
        $sitemap = is_array($this->config['sitemap'] ?? null) ? $this->config['sitemap'] : [];
        if (!($sitemap['enabled'] ?? true)) {
            return [];
        }

        $locales = is_array($this->config['locales'] ?? null) ? $this->config['locales'] : ['en'];
        $entries = [];

        if ($sitemap['include_static_pages'] ?? true) {
            foreach ($this->config['pages'] ?? [] as $route => $page) {
                if (!is_array($page) || ($page['in_sitemap'] ?? true) !== true) {
                    continue;
                }
                foreach ($locales as $locale) {
                    if (!is_string($locale)) {
                        continue;
                    }
                    $path = $this->paths->pagePath((string) $route, $locale);
                    if ($path === null) {
                        continue;
                    }
                    $entries[] = [
                        'loc'        => $this->paths->absoluteUrl($request, $path),
                        'changefreq' => (string) ($page['sitemap_changefreq'] ?? 'weekly'),
                        'priority'   => number_format((float) ($page['sitemap_priority'] ?? 0.8), 1, '.', ''),
                    ];
                }
            }
        }

        if ($sitemap['include_configured_slugs'] ?? true) {
            foreach ($this->config['slugs'] ?? [] as $route => $bySlug) {
                if (!is_array($bySlug)) {
                    continue;
                }
                $slugRoute = is_array($this->config['slug_routes'][$route] ?? null) ? $this->config['slug_routes'][$route] : [];
                foreach ($bySlug as $slug => $cfg) {
                    if (!is_array($cfg) || ($cfg['in_sitemap'] ?? true) !== true) {
                        continue;
                    }
                    foreach ($locales as $locale) {
                        if (!is_string($locale)) {
                            continue;
                        }
                        $path = $this->paths->slugPath((string) $route, $locale, (string) $slug);
                        if ($path === null) {
                            continue;
                        }
                        $entries[] = [
                            'loc'        => $this->paths->absoluteUrl($request, $path),
                            'changefreq' => (string) ($slugRoute['sitemap_changefreq'] ?? 'weekly'),
                            'priority'   => number_format((float) ($slugRoute['sitemap_priority'] ?? 0.6), 1, '.', ''),
                        ];
                    }
                }
            }
        }

        return $entries;
    }

    /**
     * @param list<array{loc: string, changefreq: string, priority: string}> $entries
     */
    public function toXml(array $entries): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($entries as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($entry['loc'], ENT_XML1) . "</loc>\n";
            $xml .= '    <changefreq>' . htmlspecialchars($entry['changefreq'], ENT_XML1) . "</changefreq>\n";
            $xml .= '    <priority>' . htmlspecialchars($entry['priority'], ENT_XML1) . "</priority>\n";
            $xml .= "  </url>\n";
        }

        return $xml . '</urlset>';
    }
}
