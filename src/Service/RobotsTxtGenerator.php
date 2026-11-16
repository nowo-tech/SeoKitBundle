<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\Request;

use function is_array;
use function is_string;
use function preg_match;
use function strlen;

/**
 * Builds robots.txt content (FrankenPHP / php-fpm / Nginx agnostic — served by Symfony).
 *
 * Every user agent and path is written on its own line, so a value with a line break or another control character
 * (e.g. from a host {@see GeoRobotsGroupsProviderInterface} fed by a database) would add its own directives. Such
 * values are dropped with a logged warning: an extra group with an invalid user agent is skipped whole, an invalid
 * path is skipped, and an invalid main `robots.user_agent` falls back to `*`.
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
        private ?LoggerInterface $logger = null,
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
        $robots    = is_array($this->config['robots'] ?? null) ? $this->config['robots'] : [];
        $lines     = [];
        $mainAgent = $robots['user_agent'] ?? '*';
        $lines[]   = 'User-agent: ' . (is_string($mainAgent) && $this->isSingleLine($mainAgent, 'user agent') ? $mainAgent : '*');

        $indexable = $this->isIndexable();

        if (!$indexable) {
            $lines[] = 'Disallow: /';
        } else {
            foreach ($robots['allow'] ?? ['/'] as $allow) {
                if (is_string($allow) && $this->isSingleLine($allow, 'path')) {
                    $lines[] = 'Allow: ' . $allow;
                }
            }
            foreach ($robots['disallow'] ?? [] as $disallow) {
                if (is_string($disallow) && $this->isSingleLine($disallow, 'path')) {
                    $lines[] = 'Disallow: ' . $disallow;
                }
            }

            foreach ($this->extraGroups($robots) as $group) {
                $agent = $group['user_agent'] ?? '';
                if (!is_string($agent) || $agent === '' || !$this->isSingleLine($agent, 'user agent')) {
                    continue;
                }
                $lines[] = '';
                $lines[] = 'User-agent: ' . $agent;
                $allow   = $group['allow'] ?? [];
                $deny    = $group['disallow'] ?? [];
                if (is_array($allow)) {
                    foreach ($allow as $path) {
                        if (is_string($path) && $path !== '' && $this->isSingleLine($path, 'path')) {
                            $lines[] = 'Allow: ' . $path;
                        }
                    }
                }
                if (is_array($deny)) {
                    foreach ($deny as $path) {
                        if (is_string($path) && $path !== '' && $this->isSingleLine($path, 'path')) {
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
     * Whether a robots.txt value stays on one line: no C0/C1 control character (CR, LF, NUL, …), no Unicode line or
     * paragraph separator, valid UTF-8. Otherwise the value is dropped and a warning logged (without the value).
     */
    private function isSingleLine(string $value, string $kind): bool
    {
        if (preg_match('/[\x{0}-\x{1F}\x{7F}-\x{9F}\x{2028}\x{2029}]/u', $value) === 0) {
            return true;
        }

        $this->logger?->warning('nowo_seo_kit: dropped a robots.txt {kind} containing a line break or control character.', [
            'kind'   => $kind,
            'length' => strlen($value),
        ]);

        return false;
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
