<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\DependencyInjection\Compiler;

use Doctrine\DBAL\Connection;
use Nowo\SeoKitBundle\DependencyInjection\Compiler\PrivateEntityManagerPass;
use Nowo\SeoKitBundle\Doctrine\PrivateEntityManagerMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function method_exists;

final class PrivateEntityManagerPassTest extends TestCase
{
    public function testAttachesTheMiddlewareToTheSourceManagerConnectionOnly(): void
    {
        $container = $this->container();
        $container->setParameter('doctrine.default_entity_manager', 'main');
        $container->setDefinition('doctrine.orm.main_entity_manager', new Definition('EM', [new Reference('doctrine.dbal.app_connection')]));

        (new PrivateEntityManagerPass())->process($container);

        self::assertSame([['connection' => 'app']], $container->getDefinition(PrivateEntityManagerMiddleware::class)->getTag('doctrine.middleware'));
    }

    public function testFallsBackToTheDefaultConnection(): void
    {
        $container = $this->container();
        $container->setParameter('doctrine.default_connection', 'primary');
        (new PrivateEntityManagerPass())->process($container);
        self::assertSame([['connection' => 'primary']], $container->getDefinition(PrivateEntityManagerMiddleware::class)->getTag('doctrine.middleware'));

        $container = $this->container();
        (new PrivateEntityManagerPass())->process($container);
        self::assertSame([['connection' => 'default']], $container->getDefinition(PrivateEntityManagerMiddleware::class)->getTag('doctrine.middleware'));
    }

    public function testDoesNothingWithoutThePrivateManager(): void
    {
        $container = new ContainerBuilder();
        (new PrivateEntityManagerPass())->process($container);
        self::assertFalse($container->hasDefinition(PrivateEntityManagerMiddleware::class));
        // doctrine/dbal 4 (no Connection::getEventManager()) supports it; DBAL 3 (CI "Real databases", DBAL 3 cells) does not.
        self::assertSame(!method_exists(Connection::class, 'getEventManager'), PrivateEntityManagerPass::supportsRollbackMiddleware());
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(PrivateEntityManagerMiddleware::class)->addTag('doctrine.middleware');

        return $container;
    }
}
