<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\Request;

use function is_array;
use function is_string;

/**
 * Builds robots.txt content (FrankenPHP / php-fpm / Nginx agnostic — served by Symfony).
 */
final readonly class RobotsTxtGenerator
{
    /**
     * @param array<string, mixed> $config
     * @param iterable<SiteIndexabilityProviderInterface> $indexabilityProviders
     * @param iterable<GeoRobotsGroupsProviderInterface> $geoRobotsGroupsProviders
     */
    public function __construct(
        private array $config,
        private SeoPathBuilderInterface $paths,
        #[TaggedIterator('nowo_seo_kit.indexability_provider')]
        private iterable $indexabilityProviders = [],
        #[TaggedIterator('nowo_seo_kit.geo_robots_groups_provider')]
        private iterable $geoRobotsGroupsProviders = [],
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

    public function generate(Request $request): string
    {
        $robots  = is_array($this->config['robots'] ?? null) ? $this->config['robots'] : [];
        $lines   = [];
        $lines[] = 'User-agent: ' . ($robots['user_agent'] ?? '*');

        $indexable = $this->isIndexable();

        if (!$indexable) {
            $lines[] = 'Disallow: /';
        } else {
            foreach ($robots['allow'] ?? ['/'] as $allow) {
                if (is_string($allow)) {
                    $lines[] = 'Allow: ' . $allow;
                }
            }
            foreach ($robots['disallow'] ?? [] as $disallow) {
                if (is_string($disallow)) {
                    $lines[] = 'Disallow: ' . $disallow;
                }
            }

            foreach ($this->extraGroups($robots) as $group) {
                $agent = $group['user_agent'] ?? '';
                if (!is_string($agent) || $agent === '') {
                    continue;
                }
                $lines[] = '';
                $lines[] = 'User-agent: ' . $agent;
                $allow   = $group['allow'] ?? [];
                $deny    = $group['disallow'] ?? [];
                if (is_array($allow)) {
                    foreach ($allow as $path) {
                        if (is_string($path) && $path !== '') {
                            $lines[] = 'Allow: ' . $path;
                        }
                    }
                }
                if (is_array($deny)) {
                    foreach ($deny as $path) {
                        if (is_string($path) && $path !== '') {
                            $lines[] = 'Disallow: ' . $path;
                        }
                    }
                }
            }
        }

        $sitemap = is_array($this->config['sitemap'] ?? null) ? $this->config['sitemap'] : [];
        if ($indexable && ($robots['sitemap_link'] ?? true) && ($sitemap['enabled'] ?? true)) {
            $path    = (string) ($sitemap['path'] ?? '/sitemap.xml');
            $lines[] = 'Sitemap: ' . $this->paths->absoluteUrl($request, $path);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * YAML {@code robots.groups} first, then tagged {@see GeoRobotsGroupsProviderInterface} rows.
     *
     * @param array<string, mixed> $robots
     *
     * @return list<array<string, mixed>>
     */
    private function extraGroups(array $robots): array
    {
        $groups = [];
        $yaml   = $robots['groups'] ?? [];
        if (is_array($yaml)) {
            foreach ($yaml as $group) {
                if (is_array($group)) {
                    $groups[] = $group;
                }
            }
        }

        foreach ($this->geoRobotsGroupsProviders as $provider) {
            foreach ($provider->groups() as $group) {
                $groups[] = $group;
            }
        }

        return $groups;
    }
}
