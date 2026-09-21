<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service\Persistence;

use Nowo\SeoKitBundle\Model\SeoSiteConfig;

/**
 * Cached reader for editable site SEO settings.
 */
interface SeoSiteConfigProviderInterface
{
    public function get(): SeoSiteConfig;

    /**
     * Drops the snapshot after an edit so the next request renders what was just saved.
     */
    public function refresh(): void;
}
