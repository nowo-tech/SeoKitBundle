<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\SeoKitBundle\Entity\SeoSurface;

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

    public function save(SeoSurface $surface): void
    {
        $this->getEntityManager()->persist($surface);
        $this->flushOrResetClosedManager($this->managerRegistry);
    }

    public function remove(SeoSurface $surface): void
    {
        $this->getEntityManager()->remove($surface);
        $this->flushOrResetClosedManager($this->managerRegistry);
    }
}
