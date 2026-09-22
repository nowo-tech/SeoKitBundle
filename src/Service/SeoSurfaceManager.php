<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;

/**
 * Load / save / clear surface overrides; empty rows are deleted so "inherit" stays a missing row.
 */
final readonly class SeoSurfaceManager
{
    public function __construct(
        private SeoSurfaceRepository $surfaces,
    ) {
    }

    public function find(string $surfaceKey, string $locale): ?SeoSurface
    {
        return $this->surfaces->findOneByKeyAndLocale($surfaceKey, $locale);
    }

    public function getOrCreate(string $surfaceKey, string $locale): SeoSurface
    {
        return $this->surfaces->getOrCreate($surfaceKey, $locale);
    }

    /**
     * Persists when any field is set; removes the row when every override is empty.
     *
     * @return bool true when a row remains, false when cleared / never stored
     */
    public function saveOrClear(SeoSurface $surface): bool
    {
        if ($this->isEmpty($surface)) {
            if ($surface->getId() !== null) {
                $this->surfaces->remove($surface);
            }

            return false;
        }

        $this->surfaces->save($surface);

        return true;
    }

    public function isEmpty(SeoSurface $seo): bool
    {
        return $seo->getMetaTitle() === null
            && $seo->getMetaDescription() === null
            && $seo->getMetaRobots() === null
            && $seo->getCanonicalOverride() === null
            && $seo->getOpenGraphImage() === null
            && $seo->getStructuredDataExtra() === null;
    }
}
