<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Event\SeoSurfaceChangedEvent;
use Nowo\SeoKitBundle\Model\SeoSurfaceData;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;

/**
 * Load / save / clear surface overrides; empty rows are deleted so "inherit" stays a missing row. Since 1.22 host code
 * type-hints {@see SeoSurfaceManagerInterface}; since 1.24.0 the entity-free {@see SeoSurfaceStoreInterface}.
 */
final readonly class SeoSurfaceManager implements SeoSurfaceManagerInterface, SeoSurfaceStoreInterface
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

    public function findData(string $surfaceKey, string $locale): ?SeoSurfaceData
    {
        $surface = $this->surfaces->findOneByKeyAndLocale($surfaceKey, $locale);

        return $surface instanceof SeoSurface ? new SeoSurfaceData(
            $surface->getSurfaceKey(),
            $surface->getLocale(),
            $surface->getMetaTitle(),
            $surface->getMetaDescription(),
            $surface->getMetaRobots(),
            $surface->getCanonicalOverride(),
            $surface->getOpenGraphImage(),
            $surface->getStructuredDataExtra(),
        ) : null;
    }

    public function save(SeoSurfaceData $data): bool
    {
        // A missing row is built in memory only: unlike getOrCreate(), an all-empty override never writes a row.
        $surface = $this->surfaces->findOneByKeyAndLocale($data->surfaceKey, $data->locale)
            ?? (new SeoSurface())->setSurfaceKey($data->surfaceKey)->setLocale($data->locale);
        $surface
            ->setMetaTitle($data->metaTitle)
            ->setMetaDescription($data->metaDescription)
            ->setMetaRobots($data->metaRobots)
            ->setCanonicalOverride($data->canonicalOverride)
            ->setOpenGraphImage($data->openGraphImage)
            ->setStructuredDataExtra($data->structuredDataExtra);

        return $this->saveOrClear($surface);
    }

    public function clear(string $surfaceKey, string $locale): bool
    {
        $surface = $this->surfaces->findOneByKeyAndLocale($surfaceKey, $locale);
        $id      = $surface?->getId();
        if (!$surface instanceof SeoSurface || $id === null) {
            return false;
        }

        // @igor-ignore - Service coordinates I/O or request-scoped work; not unsafe worker singleton state.
        $this->surfaces->remove($surface);
        $this->notifier->surfaceChanged($surface->getSurfaceKey(), $surface->getLocale(), $id, true);

        return true;
    }
}
