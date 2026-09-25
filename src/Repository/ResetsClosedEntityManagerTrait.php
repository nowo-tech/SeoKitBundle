<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Repository;

use Doctrine\Persistence\ManagerRegistry;
use Throwable;

/**
 * Flushes through the repository's entity manager and resets it when a failed flush closed it,
 * so a long-running worker can keep using Doctrine on the next request.
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
            if (!$entityManager->isOpen()) {
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
