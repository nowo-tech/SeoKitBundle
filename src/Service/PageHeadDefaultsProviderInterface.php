<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Model\PageHeadDefaults;

interface PageHeadDefaultsProviderInterface
{
    public function forLocale(string $locale): PageHeadDefaults;
}
