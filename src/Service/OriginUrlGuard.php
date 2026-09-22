<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

/**
 * Validates that a stored URL stays on the configured site origin (or is site-relative).
 */
final readonly class OriginUrlGuard
{
    public function __construct(
        private ?string $origin = null,
    ) {
    }

    public function origin(): ?string
    {
        return $this->origin;
    }

    public function belongsToSite(string $url): bool
    {
        if ($this->origin === null || $this->origin === '') {
            return str_starts_with($url, '/');
        }

        return str_starts_with($url, $this->origin . '/') || $url === $this->origin;
    }

    /**
     * Rejects protocol-relative, backslash tricks, and off-origin absolute URLs.
     */
    public function isSafeCanonical(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return true;
        }

        $url = trim($value);
        if (str_starts_with($url, '//') || str_contains($url, '\\')) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        return $this->belongsToSite($url);
    }

    /**
     * Site-relative path or https absolute URL (not http / protocol-relative).
     */
    public function isSafeOpenGraphImage(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return true;
        }

        $url = trim($value);
        if (str_starts_with($url, '//') || str_contains($url, '\\')) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        return preg_match('#^https://#i', $url) === 1;
    }
}
