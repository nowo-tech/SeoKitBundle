<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Doctrine;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;

use function str_starts_with;
use function strrpos;
use function substr;

/**
 * The Doctrine registry the SeoKit repositories see (1.24.0, rule 6, private entity manager only): the SeoKit
 * entities resolve to {@see SeoKitEntityManager}, everything else to the application's registry unchanged. The
 * repositories therefore load and write the SeoKit rows through the private unit of work.
 *
 * @internal
 */
final readonly class SeoKitManagerRegistry implements ManagerRegistry
{
    public const ENTITY_NAMESPACE = 'Nowo\\SeoKitBundle\\Entity';

    /**
     * @param ManagerRegistry $registry The application's Doctrine registry
     * @param SeoKitEntityManager $entityManager The bundle's private manager
     */
    public function __construct(
        private ManagerRegistry $registry,
        private SeoKitEntityManager $entityManager,
    ) {
    }

    public function getDefaultConnectionName(): string
    {
        return $this->registry->getDefaultConnectionName();
    }

    public function getConnection(?string $name = null): object
    {
        return $this->registry->getConnection($name);
    }

    public function getConnections(): array
    {
        return $this->registry->getConnections();
    }

    public function getConnectionNames(): array
    {
        return $this->registry->getConnectionNames();
    }

    public function getDefaultManagerName(): string
    {
        return $this->registry->getDefaultManagerName();
    }

    public function getManager(?string $name = null): ObjectManager
    {
        return $this->registry->getManager($name);
    }

    public function getManagers(): array
    {
        return $this->registry->getManagers();
    }

    /**
     * Resets an application manager on the application's behalf only: the bundle itself never calls it.
     */
    public function resetManager(?string $name = null): ObjectManager
    {
        return $this->registry->resetManager($name);
    }

    public function getManagerNames(): array
    {
        return $this->registry->getManagerNames();
    }

    public function getRepository(string $persistentObject, ?string $persistentManagerName = null): ObjectRepository
    {
        if ($persistentManagerName === null && self::isBundleEntity($persistentObject)) {
            return $this->entityManager->getRepository($persistentObject); // @phpstan-ignore return.type (the repository of that class)
        }

        return $this->registry->getRepository($persistentObject, $persistentManagerName);
    }

    public function getManagerForClass(string $class): ?ObjectManager
    {
        if (self::isBundleEntity($class)) {
            return $this->entityManager;
        }

        return $this->registry->getManagerForClass($class);
    }

    /**
     * Whether a class (or a Doctrine proxy class of it, `…\__CG__\…`) is a SeoKit entity.
     */
    public static function isBundleEntity(string $class): bool
    {
        $marker = strrpos($class, '\\__CG__\\');
        if ($marker !== false) {
            $class = substr($class, $marker + 8);
        }

        return str_starts_with($class, self::ENTITY_NAMESPACE . '\\');
    }
}
