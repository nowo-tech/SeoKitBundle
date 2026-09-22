<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Model\StructuredData\StructuredDataGraph;

interface PageHeadSiteGraphProviderInterface
{
    public function forLocale(string $locale): StructuredDataGraph;
}
