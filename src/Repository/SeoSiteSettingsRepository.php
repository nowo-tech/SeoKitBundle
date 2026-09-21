<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\SeoKitBundle\Entity\SeoSiteSettings;

/**
 * @extends ServiceEntityRepository<SeoSiteSettings>
 */
class SeoSiteSettingsRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly string $environmentRobots = 'index, follow',
        private readonly string $environmentContactEmail = '',
        private readonly string $environmentSiteName = '',
    ) {
        parent::__construct($registry, SeoSiteSettings::class);
    }

    /**
     * Returns the singleton row, seeding it from environment defaults on first read.
     */
    public function getOrCreate(): SeoSiteSettings
    {
        $settings = $this->find(SeoSiteSettings::SINGLETON_ID);
        if ($settings instanceof SeoSiteSettings) {
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
        $this->getEntityManager()->flush();

        return $settings;
    }

    public function save(SeoSiteSettings $settings): void
    {
        $settings->touchUpdatedAt();
        $this->getEntityManager()->persist($settings);
        $this->getEntityManager()->flush();
    }
}
