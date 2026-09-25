<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\SeoKitBundle\Entity\SeoSiteSettings;

/**
 * @extends ServiceEntityRepository<SeoSiteSettings>
 */
class SeoSiteSettingsRepository extends ServiceEntityRepository
{
    use ResetsClosedEntityManagerTrait;

    private readonly ManagerRegistry $managerRegistry;

    public function __construct(
        ManagerRegistry $registry,
        private readonly string $environmentRobots = 'index, follow',
        private readonly string $environmentContactEmail = '',
        private readonly string $environmentSiteName = '',
    ) {
        parent::__construct($registry, SeoSiteSettings::class);
        $this->managerRegistry = $registry;
    }

    /**
     * Returns the singleton row, seeding it from environment defaults on first read.
     *
     * An already managed row is refreshed from the database: in a long-running worker the identity map may hold
     * a copy loaded by an earlier request, and the snapshot built from it is written to the shared cache.
     */
    public function getOrCreate(): SeoSiteSettings
    {
        $settings = $this->find(SeoSiteSettings::SINGLETON_ID);
        if ($settings instanceof SeoSiteSettings) {
            $this->getEntityManager()->refresh($settings);

            return $settings;
        }

        $settings = new SeoSiteSettings();
        $settings->setDefaultRobots($this->environmentRobots);
        $settings->setIndexable(!str_contains(strtolower($this->environmentRobots), 'noindex'));
        $settings->setContactEmail($this->environmentContactEmail === '' ? null : $this->environmentContactEmail);
        if ($this->environmentSiteName !== '') {
            $settings->setSiteName($this->environmentSiteName);
        }

        $this->getEntityManager()->persist($settings);

        try {
            $this->flushOrResetClosedManager($this->managerRegistry);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->find(SeoSiteSettings::SINGLETON_ID);
            if ($existing instanceof SeoSiteSettings) {
                return $existing;
            }

            throw $exception;
        }

        return $settings;
    }

    public function save(SeoSiteSettings $settings): void
    {
        $settings->touchUpdatedAt();
        $this->getEntityManager()->persist($settings);
        $this->flushOrResetClosedManager($this->managerRegistry);
    }
}
