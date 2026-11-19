<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\SeoKitBundle\DependencyInjection\Compiler\CommitTrackingPass;
use Nowo\SeoKitBundle\DependencyInjection\Compiler\EntityManagerOptionPass;
use Nowo\SeoKitBundle\EventSubscriber\SeoKitConflictListener;
use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use Nowo\TransactionDoctrine\WriteUnit\Dbal\CommitTrackingMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * 1.25 (audit UE-CF-1): the bundle's commit tracking wraps its own connection only, or reuses transaction-doctrine's ledger.
 */
final class CommitTrackingPassTest extends TestCase
{
    public function testWrapsTheConnectionOfTheConfiguredEntityManagerOnly(): void
    {
        $container = $this->container();
        $container->setParameter(EntityManagerOptionPass::PARAMETER, 'seo');
        $container->setParameter('doctrine.default_entity_manager', 'default');
        $container->setDefinition('doctrine.orm.seo_entity_manager', new Definition('EM', [new Reference('doctrine.dbal.seo_db_connection')]));

        (new CommitTrackingPass())->process($container);

        self::assertSame([['connection' => 'seo_db']], $container->getDefinition(CommitTrackingPass::MIDDLEWARE)->getTag('doctrine.middleware'));
    }

    public function testWrapsTheConnectionOfTheDefaultEntityManager(): void
    {
        $container = $this->container();
        $container->setParameter(EntityManagerOptionPass::PARAMETER, null);
        $container->setParameter('doctrine.default_entity_manager', 'main');
        $container->setDefinition('doctrine.orm.main_entity_manager', new Definition('EM', [new Reference('doctrine.dbal.app_connection')]));

        (new CommitTrackingPass())->process($container);

        self::assertSame([['connection' => 'app']], $container->getDefinition(CommitTrackingPass::MIDDLEWARE)->getTag('doctrine.middleware'));
    }

    public function testFallsBackToTheDefaultConnection(): void
    {
        $container = $this->container();
        $container->setParameter('doctrine.default_connection', 'primary');
        (new CommitTrackingPass())->process($container);
        self::assertSame([['connection' => 'primary']], $container->getDefinition(CommitTrackingPass::MIDDLEWARE)->getTag('doctrine.middleware'));

        $container = $this->container();
        (new CommitTrackingPass())->process($container);
        self::assertSame([['connection' => 'default']], $container->getDefinition(CommitTrackingPass::MIDDLEWARE)->getTag('doctrine.middleware'));
    }

    public function testReusesTransactionDoctrineLedgerAndDropsTheBundleMiddleware(): void
    {
        $container = $this->container();
        $container->register('nowo_transaction_doctrine.commit_ledger', CommitLedger::class);
        $container->setAlias(CommitLedger::class, 'nowo_transaction_doctrine.commit_ledger');

        (new CommitTrackingPass())->process($container);

        self::assertFalse($container->hasDefinition(CommitTrackingPass::MIDDLEWARE));
        self::assertFalse($container->hasDefinition(CommitTrackingPass::LEDGER));
        self::assertEquals(new Reference(CommitLedger::class), $container->getDefinition(SeoKitConflictListener::class)->getArgument(1));
    }

    public function testReusesTheLedgerWithoutAListener(): void
    {
        $container = $this->container();
        $container->removeDefinition(SeoKitConflictListener::class);
        $container->register(CommitLedger::class, CommitLedger::class);

        (new CommitTrackingPass())->process($container);

        self::assertFalse($container->hasDefinition(CommitTrackingPass::MIDDLEWARE));
    }

    public function testDoesNothingWhenTrackingIsOff(): void
    {
        $container = new ContainerBuilder();
        (new CommitTrackingPass())->process($container);
        self::assertFalse($container->hasDefinition(CommitTrackingPass::MIDDLEWARE));
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(CommitTrackingPass::LEDGER, new Definition(CommitLedger::class));
        $container->setDefinition(CommitTrackingPass::MIDDLEWARE, (new Definition(CommitTrackingMiddleware::class, [new Reference(CommitTrackingPass::LEDGER)]))->addTag('doctrine.middleware'));
        $container->setDefinition(SeoKitConflictListener::class, new Definition(SeoKitConflictListener::class, [null, new Reference(CommitTrackingPass::LEDGER), null]));

        return $container;
    }
}
