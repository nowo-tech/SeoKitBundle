<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Enum\SitemapChangeFreq;
use Nowo\SeoKitBundle\Model\SitemapContext;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\Request;

use function is_array;
use function is_string;
use function spl_object_id;
use function sprintf;
use function str_contains;
use function strtolower;

use const ENT_QUOTES;
use const ENT_XML1;

/**
 * Builds sitemap.xml entries from configured static pages, explicit slugs, and host providers.
 */
final readonly class SitemapGenerator
{
    /**
     * @param array<string, mixed> $config
     * @param SeoPathBuilderInterface $paths Since 1.24.0 its {@see SeoUrlBuilderInterface} side is used when it
     *                                       implements it (the bundle's {@see SeoPathBuilder} does)
     * @param iterable<SitemapEntryProviderInterface|SitemapUrlProviderInterface> $urlProviders
     * @param iterable<SiteIndexabilityProviderInterface> $indexabilityProviders
     */
    public function __construct(
        private array $config,
        private SeoPathBuilderInterface $paths,
        #[TaggedIterator('nowo_seo_kit.sitemap_url_provider')]
        private iterable $urlProviders = [],
        #[TaggedIterator('nowo_seo_kit.indexability_provider')]
        private iterable $indexabilityProviders = [],
    ) {
    }

    public function isIndexable(): bool
    {
        foreach ($this->indexabilityProviders as $provider) {
            if (!$provider->isIndexable()) {
                return false;
            }
        }

        return ($this->config['indexable'] ?? true) === true;
    }

    /**
     * Entries for an HTTP request (the sitemap controller).
     *
     * @return list<array{loc: string, changefreq: string, priority: string, lastmod?: string, alternates?: list<array{hreflang: string, href: string}>}>
     */
    public function entries(Request $request): array
    {
        return $this->build(SitemapContextFactory::fromRequest($request), $request);
    }

    /**
     * Entries without an HTTP request (since 1.24.0): console commands, Messenger handlers, DDD application services.
     *
     * @return list<array{loc: string, changefreq: string, priority: string, lastmod?: string, alternates?: list<array{hreflang: string, href: string}>}>
     */
    public function entriesFor(SitemapContext $context): array
    {
        return $this->build($context, null);
    }

    /**
     * @param Request|null $request Only for the deprecated request-typed ports; rebuilt from the context when null
     *
     * @return list<array{loc: string, changefreq: string, priority: string, lastmod?: string, alternates?: list<array{hreflang: string, href: string}>}>
     */
    private function build(SitemapContext $context, ?Request $request): array
    {
        if (!$this->isIndexable()) {
            return [];
        }

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
                    if ($path === null || $this->isNoindex($page, $locale)) {
                        continue;
                    }
                    $entries[] = [
                        'loc'        => $this->absoluteUrl($context, $request, $path),
                        'changefreq' => (string) ($page['sitemap_changefreq'] ?? SitemapChangeFreq::Weekly->value),
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
                        if ($path === null || $this->isNoindex($cfg, $locale)) {
                            continue;
                        }
                        $entries[] = [
                            'loc'        => $this->absoluteUrl($context, $request, $path),
                            'changefreq' => (string) ($slugRoute['sitemap_changefreq'] ?? SitemapChangeFreq::Weekly->value),
                            'priority'   => number_format((float) ($slugRoute['sitemap_priority'] ?? 0.6), 1, '.', ''),
                        ];
                    }
                }
            }
        }

        $seen = [];
        foreach ($this->urlProviders as $provider) {
            // A provider that implements both interfaces is autoconfigured with the tag twice: call it once.
            $providerId = spl_object_id($provider);
            if (isset($seen[$providerId])) {
                continue;
            }
            $seen[$providerId] = true;
            /** @var list<array<string, mixed>> $providerEntries */
            $providerEntries = $provider instanceof SitemapEntryProviderInterface
                ? $provider->getEntriesFor($context)
                : $provider->getEntries($request ?? $this->requestFor($context));
            foreach ($providerEntries as $entry) {
                if (!isset($entry['loc']) || !is_string($entry['loc']) || $entry['loc'] === '') {
                    continue;
                }
                $normalized = [
                    'loc'        => $entry['loc'],
                    'changefreq' => is_string($entry['changefreq'] ?? null) ? $entry['changefreq'] : SitemapChangeFreq::Weekly->value,
                    'priority'   => isset($entry['priority'])
                        ? number_format((float) $entry['priority'], 1, '.', '')
                        : '0.5',
                ];
                if (isset($entry['lastmod']) && is_string($entry['lastmod'])) {
                    $normalized['lastmod'] = $entry['lastmod'];
                }
                if (isset($entry['alternates']) && is_array($entry['alternates'])) {
                    $alts = [];
                    foreach ($entry['alternates'] as $alt) {
                        if (
                            is_array($alt)
                            && isset($alt['hreflang'], $alt['href'])
                            && is_string($alt['hreflang'])
                            && is_string($alt['href'])
                        ) {
                            $alts[] = ['hreflang' => $alt['hreflang'], 'href' => $alt['href']];
                        }
                    }
                    if ($alts !== []) {
                        $normalized['alternates'] = $alts;
                    }
                }
                $entries[] = $normalized;
            }
        }

        return $entries;
    }

    private function absoluteUrl(SitemapContext $context, ?Request $request, string $path): string
    {
        if ($this->paths instanceof SeoUrlBuilderInterface) {
            return $this->paths->absoluteUrlFor($context, $path);
        }

        // A host builder that implements only the deprecated request-typed port (removed in 2.0).
        return $this->paths->absoluteUrl($request ?? $this->requestFor($context), $path);
    }

    /**
     * Synthetic request for the deprecated request-typed ports when entries are built without HTTP (removed in 2.0).
     */
    private function requestFor(SitemapContext $context): Request
    {
        $request = Request::create($context->baseUrl() . '/');
        if ($context->locale !== null) {
            $request->setLocale($context->locale);
        }

        return $request;
    }

    /**
     * A noindex page must not be advertised in the sitemap (locale robots override the base layer).
     *
     * @param array<mixed> $cfg
     */
    private function isNoindex(array $cfg, string $locale): bool
    {
        if (($cfg['noindex'] ?? false) === true) {
            return true;
        }

        $localeCfg = is_array($cfg['locales'][$locale] ?? null) ? $cfg['locales'][$locale] : [];
        $robots    = is_string($localeCfg['robots'] ?? null) && $localeCfg['robots'] !== ''
            ? $localeCfg['robots']
            : ($cfg['robots'] ?? null);

        return is_string($robots) && str_contains(strtolower($robots), 'noindex');
    }

    /**
     * @param list<array{loc: string, changefreq: string, priority: string, lastmod?: string, alternates?: list<array{hreflang: string, href: string}>}> $entries
     */
    public function toXml(array $entries): string
    {
        $hasAlternates = false;
        foreach ($entries as $entry) {
            if (($entry['alternates'] ?? []) !== []) {
                $hasAlternates = true;
                break;
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= $hasAlternates
            ? '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n"
            : '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($entries as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($entry['loc'], ENT_XML1 | ENT_QUOTES) . "</loc>\n";
            foreach ($entry['alternates'] ?? [] as $alt) {
                $xml .= sprintf(
                    '    <xhtml:link rel="alternate" hreflang="%s" href="%s"/>' . "\n",
                    htmlspecialchars($alt['hreflang'], ENT_XML1 | ENT_QUOTES),
                    htmlspecialchars($alt['href'], ENT_XML1 | ENT_QUOTES),
                );
            }
            if (isset($entry['lastmod'])) {
                $xml .= '    <lastmod>' . htmlspecialchars($entry['lastmod'], ENT_XML1 | ENT_QUOTES) . "</lastmod>\n";
            }
            $xml .= '    <changefreq>' . htmlspecialchars($entry['changefreq'], ENT_XML1 | ENT_QUOTES) . "</changefreq>\n";
            $xml .= '    <priority>' . htmlspecialchars($entry['priority'], ENT_XML1 | ENT_QUOTES) . "</priority>\n";
            $xml .= "  </url>\n";
        }

        return $xml . '</urlset>';
    }
}
