<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Database;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\AbstractManagerRegistry;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Proxy;
use Nowo\SeoKitBundle\Doctrine\SeoKitEntityManager;
use Nowo\SeoKitBundle\Doctrine\SeoKitManagerRegistry;
use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use Nowo\TransactionDoctrine\WriteUnit\Dbal\CommitTrackingMiddleware;
use PHPUnit\Framework\TestCase;

use function dirname;
use function getenv;
use function is_string;
use function sys_get_temp_dir;

use const CASE_LOWER;
use const PHP_VERSION_ID;

/**
 * Two real connections to SEO_KIT_TEST_DATABASE_URL (MySQL 8.4, MariaDB 11.4 or PostgreSQL 16; CI job "Real
 * databases"), each with a short lock timeout so a wait fails fast instead of holding the machine. Connection B, the
 * one the bundle writes through, counts its commits on a {@see CommitLedger} (transaction-doctrine's middleware, as
 * the bundle registers it on its own connection). Skipped without the variable.
 */
abstract class DatabaseTestCase extends TestCase
{
    /** Seconds a session waits for a row lock before the server answers a lock wait timeout. */
    protected const LOCK_TIMEOUT = 2;

    /** The other request (a second PHP worker). */
    protected Connection $a;

    /** The request under test, the bundle's connection. */
    protected Connection $b;

    protected CommitLedger $ledger;

    protected function setUp(): void
    {
        $url = getenv('SEO_KIT_TEST_DATABASE_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('SEO_KIT_TEST_DATABASE_URL is not set (CI job "Real databases").');
        }

        $this->ledger = new CommitLedger();
        $this->a      = $this->connect($url, null);
        $this->b      = $this->connect($url, $this->ledger);

        $manager = $this->manager($this->a);
        $tool    = new SchemaTool($manager);
        $classes = $manager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    protected function tearDown(): void
    {
        if (isset($this->a)) {
            if ($this->a->isTransactionActive()) {
                $this->a->rollBack();
            }
            $this->a->close();
        }
        if (isset($this->b)) {
            $this->b->close();
        }
    }

    /**
     * A plain entity manager on $connection (the host's manager, or the other request's).
     */
    protected function manager(Connection $connection): EntityManagerInterface
    {
        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 2) . '/src/Entity'], true);
        // DoctrineBundle's default (doctrine.orm.naming_strategy.underscore_number_aware): snake_case columns.
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        } else {
            $config->setProxyDir(sys_get_temp_dir() . '/nowo-seo-kit-database-tests/proxies');
            $config->setProxyNamespace('NowoSeoKitDatabaseTestProxies');
        }

        return new EntityManager($connection, $config);
    }

    /**
     * The registry the bundle's repositories get by default: its private entity manager over a host manager on B.
     */
    protected function bundleRegistry(): ManagerRegistry
    {
        $host = $this->hostRegistry($this->manager($this->b));

        return new SeoKitManagerRegistry($host, new SeoKitEntityManager($host));
    }

    /**
     * Rows of $table as seen by a fresh read on A.
     */
    protected function rows(string $table): int
    {
        return (int) $this->a->fetchOne('SELECT COUNT(*) FROM ' . $table);
    }

    private function connect(string $url, ?CommitLedger $ledger): Connection
    {
        $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse($url);
        $config = new Configuration();
        if ($ledger instanceof CommitLedger) {
            $config->setMiddlewares([new CommitTrackingMiddleware($ledger)]);
        }
        $connection = DriverManager::getConnection($params, $config);
        $platform   = $connection->getDatabasePlatform();
        if ($platform instanceof AbstractMySQLPlatform) {
            $connection->executeStatement('SET SESSION innodb_lock_wait_timeout = ' . self::LOCK_TIMEOUT);
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $connection->executeStatement("SET lock_timeout = '" . self::LOCK_TIMEOUT . "s'");
            $connection->executeStatement("SET statement_timeout = '30s'");
        }

        return $connection;
    }

    private function hostRegistry(EntityManagerInterface $manager): ManagerRegistry
    {
        return new class($manager) extends AbstractManagerRegistry {
            public function __construct(private readonly EntityManagerInterface $manager)
            {
                parent::__construct('ORM', ['default' => 'conn'], ['default' => 'em'], 'default', 'default', Proxy::class);
            }

            protected function getService(string $name): object
            {
                return $name === 'conn' ? $this->manager->getConnection() : $this->manager;
            }

            protected function resetService(string $name): void
            {
            }
        };
    }
}
