<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Event\SeoSurfaceChangedEvent;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;

/**
 * Load / save / clear surface overrides; empty rows are deleted so "inherit" stays a missing row. Since 1.22 host code
 * type-hints {@see SeoSurfaceManagerInterface}.
 */
final readonly class SeoSurfaceManager implements SeoSurfaceManagerInterface
{
    /**
     * @param SeoChangeNotifier $notifier Dispatches {@see SeoSurfaceChangedEvent} after a write (since 1.22)
     */
    public function __construct(
        private SeoSurfaceRepository $surfaces,
        private SeoChangeNotifier $notifier = new SeoChangeNotifier(),
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
            $id = $surface->getId();
            if ($id !== null) {
                // @igor-ignore - Service coordinates I/O or request-scoped work; not unsafe worker singleton state.
                $this->surfaces->remove($surface);
                $this->notifier->surfaceChanged($surface->getSurfaceKey(), $surface->getLocale(), $id, true);
            }

            return false;
        }

        $this->surfaces->save($surface);
        $this->notifier->surfaceChanged($surface->getSurfaceKey(), $surface->getLocale(), $surface->getId(), false);

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
