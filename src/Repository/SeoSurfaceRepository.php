<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\EventSubscriber\SeoKitConflictListener;

/**
 * @extends ServiceEntityRepository<SeoSurface>
 */
class SeoSurfaceRepository extends ServiceEntityRepository
{
    use ResetsClosedEntityManagerTrait;

    private readonly ManagerRegistry $managerRegistry;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeoSurface::class);
        $this->managerRegistry = $registry;
    }

    public function findOneByKeyAndLocale(string $surfaceKey, string $locale): ?SeoSurface
    {
        return $this->findOneBy([
            'surfaceKey' => $surfaceKey,
            'locale'     => strtolower(trim($locale)),
        ]);
    }

    public function getOrCreate(string $surfaceKey, string $locale): SeoSurface
    {
        $existing = $this->findOneByKeyAndLocale($surfaceKey, $locale);
        if ($existing instanceof SeoSurface) {
            return $existing;
        }

        $surface = new SeoSurface();
        $surface->setSurfaceKey($surfaceKey);
        $surface->setLocale($locale);

        $this->getEntityManager()->persist($surface);

        try {
            $this->flushOrResetClosedManager($this->managerRegistry);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->findOneByKeyAndLocale($surfaceKey, $locale);
            if ($existing instanceof SeoSurface) {
                return $existing;
            }

            throw $exception;
        }

        return $surface;
    }

    /**
     * Saves the surface. Prefer {@see self::upsert()}, which returns the stored row.
     */
    public function save(SeoSurface $surface): void
    {
        $this->upsert($surface);
    }

    /**
     * Saves the surface and returns the row that holds its values (1.25, REQ-RUNTIME-003 rule 4).
     *
     * One row per (key, locale) is an "only one" rule kept by `uniq_nowo_seo_surface_key_locale`. When the surface is
     * new and a concurrent request inserted the same (key, locale) first, the insert is refused by that key; its
     * overrides are then written to the stored row instead (last writer wins, as for two concurrent edits of an
     * existing row) and that row is returned: the given object belongs to the manager the failed flush closed and is
     * not stored. Any other failure, and a second refusal, is thrown (a lock conflict is never retried here: the
     * caller's route answers it, {@see SeoKitConflictListener}).
     */
    public function upsert(SeoSurface $surface): SeoSurface
    {
        // Before persist(): a sequence-generated id is assigned at persist time, an identity one at the insert.
        $new = $surface->getId() === null;
        $this->getEntityManager()->persist($surface);

        try {
            $this->flushOrResetClosedManager($this->managerRegistry);
        } catch (UniqueConstraintViolationException $exception) {
            $stored = $new ? $this->findOneByKeyAndLocale($surface->getSurfaceKey(), $surface->getLocale()) : null;
            if (!$stored instanceof SeoSurface) {
                throw $exception;
            }

            $stored
                ->setMetaTitle($surface->getMetaTitle())
                ->setMetaDescription($surface->getMetaDescription())
                ->setMetaRobots($surface->getMetaRobots())
                ->setCanonicalOverride($surface->getCanonicalOverride())
                ->setOpenGraphImage($surface->getOpenGraphImage())
                ->setStructuredDataExtra($surface->getStructuredDataExtra());
            $this->flushOrResetClosedManager($this->managerRegistry);

            return $stored;
        }

        return $surface;
    }

    public function remove(SeoSurface $surface): void
    {
        $this->getEntityManager()->remove($surface);
        $this->flushOrResetClosedManager($this->managerRegistry);
    }
}
