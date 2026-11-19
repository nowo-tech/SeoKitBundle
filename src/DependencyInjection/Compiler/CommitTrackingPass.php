<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\DependencyInjection\Compiler;

use Nowo\SeoKitBundle\EventSubscriber\SeoKitConflictListener;
use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function is_string;
use function preg_match;
use function sprintf;

/**
 * 1.25 (REQ-RUNTIME-003 rule 6): scopes the commit tracking of the conflict answer (audit UE-CF-1).
 *
 * - nowo-tech/transaction-doctrine's bundle registered with `commit_detection` (its {@see CommitLedger} service exists):
 *   its own middleware already counts every commit, so the bundle's middleware and ledger are removed and
 *   {@see SeoKitConflictListener} reads transaction-doctrine's ledger. No connection is wrapped twice.
 * - Otherwise (`conflict_response.commit_tracking: bundle`, the default): the bundle's middleware wraps only the
 *   connection of the manager that holds the SeoKit entities (`doctrine.entity_manager`, else the default manager, the
 *   source of the private manager), never the host's other connections.
 * - `off`: the extension registered neither the ledger nor the middleware; this pass does nothing.
 *
 * Runs before DoctrineBundle's MiddlewaresPass (priority 0).
 *
 * @internal
 */
final class CommitTrackingPass implements CompilerPassInterface
{
    /** Real commits counted by {@see self::MIDDLEWARE}, read by the conflict answer. */
    public const LEDGER = 'nowo_seo_kit.commit_ledger';

    /** transaction-doctrine's commit-tracking middleware, on the bundle's connection only. */
    public const MIDDLEWARE = 'nowo_seo_kit.commit_tracking_middleware';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::MIDDLEWARE)) {
            return;
        }

        if ($container->has(CommitLedger::class)) {
            $container->removeDefinition(self::MIDDLEWARE);
            $container->removeDefinition(self::LEDGER);
            if ($container->hasDefinition(SeoKitConflictListener::class)) {
                $container->getDefinition(SeoKitConflictListener::class)->replaceArgument(1, new Reference(CommitLedger::class));
            }

            return;
        }

        $container->getDefinition(self::MIDDLEWARE)
            ->clearTag('doctrine.middleware')
            ->addTag('doctrine.middleware', ['connection' => $this->bundleConnection($container)]);
    }

    /**
     * The connection of the manager that holds the SeoKit entities; the default connection when it cannot be resolved.
     */
    private function bundleConnection(ContainerBuilder $container): string
    {
        $manager = $container->hasParameter(EntityManagerOptionPass::PARAMETER) ? $container->getParameter(EntityManagerOptionPass::PARAMETER) : null;
        if (!is_string($manager) || $manager === '') {
            $manager = $container->hasParameter('doctrine.default_entity_manager') ? $container->getParameter('doctrine.default_entity_manager') : null;
        }

        $id = sprintf('doctrine.orm.%s_entity_manager', is_string($manager) && $manager !== '' ? $manager : 'default');
        // DoctrineBundle passes the connection as the manager's first argument.
        $argument = $container->hasDefinition($id) ? ($container->getDefinition($id)->getArguments()[0] ?? null) : null;
        if ($argument instanceof Reference && preg_match('/^doctrine\.dbal\.(.+)_connection$/D', (string) $argument, $match) === 1) {
            return $match[1];
        }

        $default = $container->hasParameter('doctrine.default_connection') ? $container->getParameter('doctrine.default_connection') : null;

        return is_string($default) && $default !== '' ? $default : 'default';
    }
}
