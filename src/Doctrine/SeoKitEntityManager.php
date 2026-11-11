<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Doctrine;

use Doctrine\Common\EventManager;
use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Symfony\Contracts\Service\ResetInterface;

use function array_keys;
use function is_array;
use function is_string;
use function sprintf;
use function unserialize;

/**
 * The bundle's private entity manager (1.24.0, REQ-ARCH-001 rule 6), used when `doctrine.entity_manager` is not set.
 *
 * Before 1.24.0 the zero-config path wrote the SeoKit entities through the application's default entity manager, so a
 * bundle write (surface store / manager, admin, JSON API, site settings) flushed every change the application had
 * left pending, and a failed bundle flush reset the application's manager. The bundle now writes through a
 * **private** manager created on the connection, configuration (mapping, metadata cache, proxies) and event manager
 * of the manager that maps {@see SeoSurface}. Its unit of work holds only SeoKit rows: a bundle flush never commits an
 * application change, and a failed write closes only this manager. The tables do not move. The connection is shared,
 * so a transaction the application opened around a bundle call includes the bundle's rows.
 *
 * The object is a stable decorator (repositories keep it); the wrapped manager is resolved on every call and created
 * again when:
 *
 * - it was closed (its own failed flush) or the source connection changed;
 * - a rollback (transaction or savepoint) happened on the connection since it last wrote, so its identity map may
 *   describe rows that no longer exist ({@see PrivateEntityManagerMiddleware}). A stale manager that still has
 *   scheduled inserts, updates or deletes is **kept** (replacing it would silently drop them, audit MQ-L-L1) until
 *   that work is flushed or the next unit-of-work boundary;
 * - {@see reset()} ran: at every unit-of-work boundary (SeoRuntimeClearSubscriber, with its nested-command guard) and
 *   at `kernel.reset`.
 *
 * The SQL filters enabled on the source manager, with their parameters, are mirrored on the private manager on every
 * call (audit MQ-L-L3 / MO-Y-L2), and a filter whose parameters changed is re-created, so a parameter the source no
 * longer has never survives (audit MO-Y-D-L1).
 *
 * @internal
 */
final class SeoKitEntityManager extends EntityManagerDecorator implements ResetInterface
{
    private ?EntityManagerInterface $current = null;

    /** A rollback happened on the connection since the private manager was created or last replaced. */
    private bool $stale = false;

    /**
     * @param ManagerRegistry $registry The application's Doctrine registry (the source manager is resolved per call)
     */
    public function __construct(
        private readonly ManagerRegistry $registry,
    ) {
        // No wrapped manager yet: every read of $this->wrapped goes through __get() (resolved per call).
        unset($this->{'wrapped'});
    }

    /**
     * The wrapped manager of {@see EntityManagerDecorator} (the only magic property).
     */
    public function __get(string $name): EntityManagerInterface
    {
        if ($name !== 'wrapped') {
            throw new LogicException(sprintf('Undefined property %s::$%s.', self::class, $name));
        }

        return $this->live();
    }

    /**
     * The open private manager, created (again) when needed, with the source's SQL filters.
     */
    public function live(): EntityManagerInterface
    {
        $source  = $this->source();
        $current = $this->current;
        if (!$current instanceof EntityManagerInterface || !$current->isOpen() || $current->getConnection() !== $source->getConnection()
            || ($this->stale && !$this->hasScheduledWork($current))) {
            if ($current instanceof EntityManagerInterface && $current->isOpen()) {
                $current->clear();
            }
            // DoctrineBundle's ContainerAwareEventManager extends EventManager; any other implementation gets a fresh one.
            $eventManager = $source->getEventManager();
            // @igor-ignore - Bundle-owned unit of work: dropped at every boundary, replaced when closed, stale or on another connection.
            $current = $this->current = new EntityManager($source->getConnection(), $source->getConfiguration(), $eventManager instanceof EventManager ? $eventManager : null);
            // @igor-ignore - Bundle-owned flag of the private manager, reset when the manager is replaced.
            $this->stale = false;
        }
        $this->syncFilters($source, $current);

        return $current;
    }

    /**
     * Whether a private manager exists right now (no side effect; tests).
     */
    public function isCreated(): bool
    {
        return $this->current instanceof EntityManagerInterface;
    }

    /**
     * Whether a rollback made the private manager's identity map stale (no side effect; tests).
     */
    public function isStale(): bool
    {
        return $this->stale;
    }

    /**
     * A transaction or savepoint was rolled back on the connection (told by {@see PrivateEntityManagerConnection}).
     * The manager is replaced at its next use unless it has scheduled work; it is never cleared here (the rollback
     * may run inside the private manager's own failing flush).
     */
    public function connectionRolledBack(): void
    {
        if ($this->current instanceof EntityManagerInterface) {
            // @igor-ignore - Bundle-owned flag of the private manager, cleared when the manager is replaced.
            $this->stale = true;
        }
    }

    /**
     * Detaches every row from the private manager. Never touches an application manager and never creates the private
     * one.
     */
    public function clear(): void
    {
        if ($this->current instanceof EntityManagerInterface && $this->current->isOpen()) {
            $this->current->clear();
        }
    }

    /**
     * Drops the private manager (a unit-of-work boundary or `kernel.reset`); the next call creates a fresh one.
     */
    public function reset(): void
    {
        $this->clear();
        // @igor-ignore - Boundary drop of the bundle's own manager (no state kept).
        $this->current = null;
        // @igor-ignore - Boundary drop of the bundle's own manager (no state kept).
        $this->stale = false;
    }

    /**
     * The application's manager whose connection, configuration, events and filters the private one shares. Never
     * flushed, cleared, closed or reset by the bundle.
     */
    public function source(): EntityManagerInterface
    {
        $source = $this->registry->getManagerForClass(SeoSurface::class);
        if (!$source instanceof EntityManagerInterface) {
            throw new LogicException(sprintf('No Doctrine ORM entity manager maps %s: enable nowo_seo_kit.persistence with DoctrineBundle (see docs/CONFIGURATION.md, "persistence").', SeoSurface::class));
        }

        return $source;
    }

    /**
     * Scheduled inserts, updates or deletes, including changes not computed yet (a managed entity edited in memory).
     */
    private function hasScheduledWork(EntityManagerInterface $manager): bool
    {
        $unitOfWork = $manager->getUnitOfWork();
        $unitOfWork->computeChangeSets();

        return $unitOfWork->getScheduledEntityInsertions() !== []
            || $unitOfWork->getScheduledEntityUpdates() !== []
            || $unitOfWork->getScheduledEntityDeletions() !== [];
    }

    /**
     * Mirrors the SQL filters enabled on the source manager, with their parameters, on the private one, and disables
     * the others. A filter whose serialized parameters differ is disabled and enabled again (a fresh instance) before
     * its parameters are copied, so a parameter the source no longer has (a previous tenant) never survives.
     */
    private function syncFilters(EntityManagerInterface $source, EntityManagerInterface $target): void
    {
        $wanted  = $source->getFilters()->getEnabledFilters();
        $filters = $target->getFilters();
        foreach (array_keys($filters->getEnabledFilters()) as $name) {
            if (!isset($wanted[$name])) {
                $filters->disable($name);
            }
        }
        foreach ($wanted as $name => $filter) {
            $state = (string) $filter;
            if ($filters->isEnabled($name)) {
                if ((string) $filters->getFilter($name) === $state) {
                    continue;
                }
                $filters->disable($name);
            }
            $copy = $filters->enable($name);
            /** @var array<string, array{value: mixed, type: int|string|null, is_list?: bool}> $parameters */
            $parameters = unserialize($state);
            // SQLFilter::setParameter() takes a string type or null: a non-string type was inferred, null infers it again.
            foreach ($parameters as $parameter => $definition) {
                if (($definition['is_list'] ?? false) === true && is_array($definition['value'])) {
                    $copy->setParameterList($parameter, $definition['value'], is_string($definition['type']) ? $definition['type'] : 'string');
                } else {
                    $copy->setParameter($parameter, $definition['value'], is_string($definition['type']) ? $definition['type'] : null);
                }
            }
        }
    }
}
