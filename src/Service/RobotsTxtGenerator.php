<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Symfony\Component\HttpFoundation\Request;

use function is_array;
use function is_string;

/**
 * Builds robots.txt content (FrankenPHP / php-fpm / Nginx agnostic — served by Symfony).
 */
final class RobotsTxtGenerator
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
        private readonly SeoPathBuilder $paths,
    ) {
    }

    public function generate(Request $request): string
    {
        $robots = is_array($this->config['robots'] ?? null) ? $this->config['robots'] : [];
        $lines = [];
        $lines[] = 'User-agent: '.(string) ($robots['user_agent'] ?? '*');

        foreach ($robots['allow'] ?? ['/'] as $allow) {
            if (is_string($allow)) {
                $lines[] = 'Allow: '.$allow;
            }
        }
        foreach ($robots['disallow'] ?? [] as $disallow) {
            if (is_string($disallow)) {
                $lines[] = 'Disallow: '.$disallow;
            }
        }

        $sitemap = is_array($this->config['sitemap'] ?? null) ? $this->config['sitemap'] : [];
        if (($robots['sitemap_link'] ?? true) && ($sitemap['enabled'] ?? true)) {
            $path = (string) ($sitemap['path'] ?? '/sitemap.xml');
            $lines[] = 'Sitemap: '.$this->paths->absoluteUrl($request, $path);
        }

        return implode("\n", $lines)."\n";
    }
}
