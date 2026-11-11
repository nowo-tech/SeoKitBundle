<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Ddd\Domain;

/**
 * Application port of the DDD test host (stands for a read model).
 */
final class PublishedArticles
{
    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        return ['hello-world', 'second-post'];
    }
}
