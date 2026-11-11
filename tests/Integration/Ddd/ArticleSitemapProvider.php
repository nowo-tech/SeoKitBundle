<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Ddd;

use Nowo\SeoKitBundle\Model\SitemapContext;
use Nowo\SeoKitBundle\Service\SeoUrlBuilderInterface;
use Nowo\SeoKitBundle\Service\SitemapEntryProviderInterface;
use Nowo\SeoKitBundle\Tests\Integration\Ddd\Domain\PublishedArticles;

/**
 * Infrastructure adapter of the DDD test host: implements only the framework-free sitemap port (no HTTP type).
 */
final readonly class ArticleSitemapProvider implements SitemapEntryProviderInterface
{
    public function __construct(
        private PublishedArticles $articles,
        private SeoUrlBuilderInterface $urls,
    ) {
    }

    public function getEntriesFor(SitemapContext $context): array
    {
        $entries = [];
        foreach ($this->articles->slugs() as $slug) {
            $entries[] = ['loc' => $this->urls->absoluteUrlFor($context, '/' . ($context->locale ?? 'en') . '/blog/' . $slug)];
        }

        return $entries;
    }
}
