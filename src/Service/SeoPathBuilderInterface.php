<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Builds absolute/relative SEO paths and locale alternates.
 *
 * Extracted so other bundles (e.g. RoutingKit) can decorate path resolution.
 *
 * @deprecated since 1.24.0, use {@see SeoUrlBuilderInterface} (no HTTP type in the port, REQ-ARCH-001 rule 3);
 *             removed in 2.0
 */
interface SeoPathBuilderInterface
{
    public function absoluteUrl(Request $request, string $path): string;

    public function pagePath(string $route, string $locale, ?string $fallbackPath = null): ?string;

    public function resolveCanonicalSlug(string $route, string $slug): string;

    public function slugPath(string $route, string $locale, string $slug): ?string;
}
