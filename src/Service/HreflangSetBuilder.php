<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Model\HreflangSet;

final readonly class HreflangSetBuilder
{
    /** @param list<string> $siteLocales */
    public function __construct(
        private AbsoluteUrlBuilder $urls,
        private array $siteLocales,
        private string $defaultLocale,
    ) {
    }

    /** @param array<string, string> $pathsByLocale */
    public function build(array $pathsByLocale): HreflangSet
    {
        $alternates = [];
        foreach ($this->siteLocales as $locale) {
            $path = $pathsByLocale[$locale] ?? null;
            if ($path === null || trim($path) === '') {
                continue;
            }
            $alternates[$locale] = $this->urls->fromPath($path);
        }

        return new HreflangSet($alternates, $alternates[$this->defaultLocale] ?? null);
    }
}
