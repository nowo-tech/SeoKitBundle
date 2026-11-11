<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Model\SitemapContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * HTTP edge adapter: turns a Symfony request into the framework-free {@see SitemapContext} (since 1.24.0).
 *
 * Stateless. The ports never see the request: only this adapter does.
 */
final class SitemapContextFactory
{
    public static function fromRequest(Request $request): SitemapContext
    {
        return new SitemapContext($request->getSchemeAndHttpHost(), $request->getLocale(), $request->getBasePath());
    }
}
