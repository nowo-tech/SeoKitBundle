<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Controller;

use Nowo\SeoKitBundle\Service\RobotsTxtGenerator;
use Nowo\SeoKitBundle\Service\SitemapGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves sitemap.xml and robots.txt from Symfony (works behind FrankenPHP, php-fpm, Nginx).
 */
final class SeoKitController
{
    public function __construct(
        private readonly SitemapGenerator $sitemapGenerator,
        private readonly RobotsTxtGenerator $robotsTxtGenerator,
        private readonly array $config,
    ) {
    }

    public function sitemap(Request $request): Response
    {
        if (!($this->config['sitemap']['enabled'] ?? true)) {
            return new Response('Not Found', Response::HTTP_NOT_FOUND);
        }

        $entries = $this->sitemapGenerator->entries($request);
        $xml = $this->sitemapGenerator->toXml($entries);

        return new Response($xml, Response::HTTP_OK, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function robots(Request $request): Response
    {
        if (!($this->config['robots']['enabled'] ?? true)) {
            return new Response('Not Found', Response::HTTP_NOT_FOUND);
        }

        return new Response($this->robotsTxtGenerator->generate($request), Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
