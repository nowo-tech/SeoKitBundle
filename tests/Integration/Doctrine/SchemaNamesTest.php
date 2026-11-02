<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration\Doctrine;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\SeoKitBundle\DependencyInjection\Configuration;
use Nowo\SeoKitBundle\DependencyInjection\SeoKitExtension;
use Nowo\SeoKitBundle\Doctrine\TablePrefixListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function dirname;
use function implode;
use function ksort;

use const PHP_VERSION_ID;

/**
 * Golden schema names (REQ-ORM-002): from the `nowo_seo_kit.doctrine` configuration to the generated schema. With the
 * defaults every table, index, unique and foreign-key name stays byte-identical; `table_name_prefix` replaces the
 * `nowo_seo_` part of every derived name.
 */
final class SchemaNamesTest extends TestCase
{
    public function testDefaultNamesAreUnchanged(): void
    {
        self::assertSame(self::DEFAULT_NAMES, $this->schemaNames([]));
        self::assertSame(self::DEFAULT_NAMES, $this->schemaNames(['table_name_prefix' => 'nowo_seo_', 'table_prefix' => '']));
    }

    public function testTableNamePrefixReachesEveryDerivedName(): void
    {
        self::assertSame(self::SEO_NAMES, $this->schemaNames(['table_name_prefix' => 'seo_']));
    }

    public function testTablePrefixIsPrependedAfterTheNamePrefix(): void
    {
        $names = $this->schemaNames(['table_name_prefix' => 'seo_', 'table_prefix' => 'app_']);

        self::assertSame('surface_key,locale', $names['app_seo_surface']['unique']['app_uniq_seo_surface_key_locale'] ?? null);
    }

    /**
     * Loads the extension with persistence on, wires the listener as the container would and lists the schema names.
     *
     * @param array<string, string> $doctrine `nowo_seo_kit.doctrine` options
     *
     * @return array<string, array{indexes: array<string, string>, unique: array<string, string>, fk: array<string, string>}>
     */
    private function schemaNames(array $doctrine): array
    {
        $container = new ContainerBuilder();
        (new SeoKitExtension())->load([['persistence' => ['enabled' => true], 'doctrine' => $doctrine]], $container);

        $events = new EventManager();
        if ($container->hasDefinition(TablePrefixListener::class)) {
            $tablePrefix     = $container->getParameter(Configuration::ALIAS . '.doctrine.table_prefix');
            $tableNamePrefix = $container->getParameter(Configuration::ALIAS . '.doctrine.table_name_prefix');
            self::assertIsString($tablePrefix);
            self::assertIsString($tableNamePrefix);
            $events->addEventListener(Events::loadClassMetadata, new TablePrefixListener($tablePrefix, $tableNamePrefix));
        }

        $configuration = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 3) . '/src/Entity'], true);
        if (PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $em         = new EntityManager($connection, $configuration, $events);
        $schema     = (new SchemaTool($em))->getSchemaFromMetadata($em->getMetadataFactory()->getAllMetadata());

        $names = [];
        foreach ($schema->getTables() as $table) {
            $entry = ['indexes' => [], 'unique' => [], 'fk' => []];
            foreach ($table->getIndexes() as $index) {
                if (!$index->isPrimary()) {
                    $entry[$index->isUnique() ? 'unique' : 'indexes'][$index->getName()] = implode(',', $index->getColumns());
                }
            }
            foreach ($table->getForeignKeys() as $foreignKey) {
                $entry['fk'][$foreignKey->getName()] = implode(',', $foreignKey->getLocalColumns()) . ' -> ' . $foreignKey->getForeignTableName();
            }
            ksort($entry['indexes']);
            ksort($entry['unique']);
            ksort($entry['fk']);
            $names[$table->getName()] = $entry;
        }
        ksort($names);

        return $names;
    }

    private const DEFAULT_NAMES = [
        'nowo_seo_site_settings' => [
            'indexes' => [],
            'unique'  => [],
            'fk'      => [],
        ],
        'nowo_seo_site_settings_translation' => [
            'indexes' => [
                'IDX_42535D7A59949888' => 'settings_id',
            ],
            'unique' => [
                'uniq_nowo_seo_site_settings_translation_locale' => 'settings_id,locale',
            ],
            'fk' => [
                'FK_42535D7A59949888' => 'settings_id -> nowo_seo_site_settings',
            ],
        ],
        'nowo_seo_surface' => [
            'indexes' => [],
            'unique'  => [
                'uniq_nowo_seo_surface_key_locale' => 'surface_key,locale',
            ],
            'fk' => [],
        ],
    ];

    private const SEO_NAMES = [
        'seo_site_settings' => [
            'indexes' => [],
            'unique'  => [],
            'fk'      => [],
        ],
        'seo_site_settings_translation' => [
            'indexes' => [
                'IDX_FCC327F259949888' => 'settings_id',
            ],
            'unique' => [
                'uniq_seo_site_settings_translation_locale' => 'settings_id,locale',
            ],
            'fk' => [
                'FK_FCC327F259949888' => 'settings_id -> seo_site_settings',
            ],
        ],
        'seo_surface' => [
            'indexes' => [],
            'unique'  => [
                'uniq_seo_surface_key_locale' => 'surface_key,locale',
            ],
            'fk' => [],
        ],
    ];
}
