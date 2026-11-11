<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model;

use InvalidArgumentException;
use Nowo\SeoKitBundle\Service\SitemapContextFactory;

use function parse_url;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function strtolower;
use function trim;

/**
 * Framework-free view of the request that asks for the sitemap (since 1.24.0, REQ-ARCH-001 rule 3).
 *
 * Carries what a URL provider or a path builder needs to build absolute URLs: the site origin (scheme, host and
 * optional port, no trailing slash), the current locale and the front-controller base path. It replaces the
 * `Symfony\Component\HttpFoundation\Request` argument of the deprecated sitemap / path-builder ports. Build it from a
 * request with {@see SitemapContextFactory::fromRequest()}, or by hand in a console
 * command or a Messenger handler.
 */
final readonly class SitemapContext
{
    public string $origin;

    public ?string $locale;

    public string $basePath;

    /**
     * @param string $origin Absolute origin, e.g. `https://www.example.com` (a trailing slash is removed)
     * @param string|null $locale Current locale (lower-cased), null when unknown
     * @param string $basePath Front-controller base path, e.g. `/app` (empty when the site is at the root)
     */
    public function __construct(string $origin, ?string $locale = null, string $basePath = '')
    {
        $origin = rtrim(trim($origin), '/');
        $parts  = parse_url($origin);
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || (isset($parts['path']) && $parts['path'] !== '')
            || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException(sprintf('SitemapContext origin must be an absolute origin (scheme and host, no path), got "%s".', $origin));
        }

        $basePath = rtrim(trim($basePath), '/');
        if ($basePath !== '' && !str_starts_with($basePath, '/')) {
            $basePath = '/' . $basePath;
        }

        $locale = $locale === null ? null : strtolower(trim($locale));

        $this->origin   = $origin;
        $this->locale   = $locale === '' ? null : $locale;
        $this->basePath = $basePath;
    }

    /**
     * Same origin and base path, another locale (one sitemap entry per locale).
     */
    public function withLocale(?string $locale): self
    {
        return new self($this->origin, $locale, $this->basePath);
    }

    /**
     * Origin plus base path, without a trailing slash (`https://www.example.com/app`).
     */
    public function baseUrl(): string
    {
        return $this->origin . $this->basePath;
    }
}
