<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use InvalidArgumentException;

use function sprintf;

/**
 * Builds absolute, self-referencing URLs for canonical, hreflang, Open Graph and sitemaps.
 *
 * Configured from {@see nowo_seo_kit.base_url}. Every public URL should go through this (or
 * {@see SeoPathBuilderInterface::absoluteUrl}) so origin and path shape cannot drift.
 */
final readonly class AbsoluteUrlBuilder
{
    private string $origin;

    public function __construct(string $baseUrl)
    {
        $this->origin = $this->normaliseOrigin($baseUrl);
    }

    /**
     * Absolute URL for a path already rendered by the router.
     */
    public function fromPath(string $path): string
    {
        return $this->origin . $this->normalisePath($path);
    }

    /**
     * Site origin without a trailing slash.
     */
    public function origin(): string
    {
        return $this->origin;
    }

    /**
     * Whether a stored override points at this site.
     */
    public function belongsToSite(string $url): bool
    {
        return str_starts_with($url, $this->origin . '/') || $url === $this->origin;
    }

    private function normaliseOrigin(string $publicBaseUrl): string
    {
        $trimmed = rtrim(trim($publicBaseUrl), '/');
        if ($trimmed === '') {
            throw new InvalidArgumentException('nowo_seo_kit.base_url must not be empty: canonical URLs cannot be built without an origin.');
        }

        $parts = parse_url($trimmed);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException(sprintf('nowo_seo_kit.base_url must be an absolute URL with a scheme and a host, got "%s".', $publicBaseUrl));
        }

        if (isset($parts['path']) && $parts['path'] !== '') {
            throw new InvalidArgumentException(sprintf('nowo_seo_kit.base_url must be an origin without a path, got "%s".', $publicBaseUrl));
        }

        return $trimmed;
    }

    /**
     * Canonical paths carry no query string, no fragment and no trailing slash (except `/`).
     */
    private function normalisePath(string $path): string
    {
        $withoutQuery = strtok($path, '?#');
        $clean        = $withoutQuery === false ? '' : $withoutQuery;

        if ($clean === '' || $clean === '/') {
            return '/';
        }

        if (!str_starts_with($clean, '/')) {
            $clean = '/' . $clean;
        }

        return rtrim($clean, '/');
    }
}
