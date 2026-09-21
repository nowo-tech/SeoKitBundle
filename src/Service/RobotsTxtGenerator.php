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
     */
    public function __construct(
        private array $config,
        private SeoPathBuilderInterface $paths,
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
        }

        $sitemap = is_array($this->config['sitemap'] ?? null) ? $this->config['sitemap'] : [];
        if ($indexable && ($robots['sitemap_link'] ?? true) && ($sitemap['enabled'] ?? true)) {
            $path    = (string) ($sitemap['path'] ?? '/sitemap.xml');
            $lines[] = 'Sitemap: ' . $this->paths->absoluteUrl($request, $path);
        }

        return implode("\n", $lines) . "\n";
    }
}
