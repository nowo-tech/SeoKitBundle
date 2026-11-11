<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\SeoKitBundle\Doctrine\SeoKitEntityManager;
use Throwable;

/**
 * Flushes through the repository's entity manager and recovers it when a failed flush closed it, so a long-running
 * worker can keep using Doctrine on the next request.
 *
 * - Private entity manager (1.24.0 default): the flush only commits SeoKit rows; a closed private manager is replaced
 *   on its next use. No application manager is ever reset.
 * - `doctrine.entity_manager` (dedicated, bundle-owned manager): that manager is reset through the registry.
 * - `doctrine.private_entity_manager: false` (deprecated): the pre-1.24 behaviour on the default manager.
 *
 * @internal
 */
trait ResetsClosedEntityManagerTrait
{
    private function flushOrResetClosedManager(ManagerRegistry $registry): void
    {
        $entityManager = $this->getEntityManager();

        try {
            $entityManager->flush();
        } catch (Throwable $exception) {
            if (!$entityManager instanceof SeoKitEntityManager && !$entityManager->isOpen()) {
                foreach (array_keys($registry->getManagerNames()) as $name) {
                    if ($registry->getManager($name) === $entityManager) {
                        $registry->resetManager($name);

                        break;
                    }
                }
            }

            throw $exception;
        }
    }
}
