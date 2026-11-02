<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\SeoKitBundle\Doctrine\TablePrefixListener;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use PHPUnit\Framework\TestCase;
use stdClass;

use function is_array;

final class TablePrefixListenerTest extends TestCase
{
    public function testEmptyPrefixLeavesNamesUnchanged(): void
    {
        $metadata = $this->metadata(SeoSurface::class);

        (new TablePrefixListener(''))->loadClassMetadata($this->event($metadata));

        $this->assertSame('nowo_seo_surface', $metadata->getTableName());
        $this->assertSame(['idx_seo_a', 'idx_seo_b'], array_keys($metadata->table['indexes'] ?? []));
    }

    public function testHostEntitiesAreIgnored(): void
    {
        $metadata = $this->metadata(stdClass::class);

        (new TablePrefixListener('app_'))->loadClassMetadata($this->event($metadata));

        $this->assertSame('nowo_seo_surface', $metadata->getTableName());
    }

    public function testBundleTableIndexesAndUniqueConstraintsArePrefixed(): void
    {
        $metadata                             = $this->metadata(SeoSurface::class);
        $metadata->table['uniqueConstraints'] = ['uniq_x' => ['columns' => ['id']], 0 => ['columns' => ['ip_address']]];

        (new TablePrefixListener('app_'))->loadClassMetadata($this->event($metadata));

        $this->assertSame('app_nowo_seo_surface', $metadata->getTableName());
        $this->assertSame(['app_idx_seo_a', 'app_idx_seo_b'], array_keys($metadata->table['indexes'] ?? []));
        $this->assertSame(['app_uniq_x', 0], $this->keys($metadata->table, 'uniqueConstraints'));
    }

    public function testEntitiesWithoutIndexesOnlyGetTheTablePrefix(): void
    {
        $metadata = new ClassMetadata(SeoSurface::class);
        $metadata->setPrimaryTable(['name' => 'nowo_seo_surface']);

        (new TablePrefixListener('app_'))->loadClassMetadata($this->event($metadata));

        $this->assertSame('app_nowo_seo_surface', $metadata->getTableName());
        $this->assertArrayNotHasKey('indexes', $metadata->table);
    }

    /**
     * Keys of `$metadata->table[$section]`, read through `mixed` so static analysis does not keep the pre-listener shape.
     *
     * @return list<int|string>
     */
    private function keys(mixed $table, string $section): array
    {
        $entries = is_array($table) && is_array($table[$section] ?? null) ? $table[$section] : [];

        return array_keys($entries);
    }

    public function testTableNamePrefixReplacesTheDefaultPartOfEveryName(): void
    {
        $metadata = new ClassMetadata(SeoSurface::class);
        $metadata->setPrimaryTable([
            'name'              => 'nowo_seo_surface',
            'uniqueConstraints' => ['uniq_nowo_seo_surface_key_locale' => ['columns' => ['surface_key', 'locale']], 0 => ['columns' => ['id']]],
            'indexes'           => ['idx_other' => ['columns' => ['locale']]],
        ]);

        (new TablePrefixListener('', 'seo_'))->loadClassMetadata($this->event($metadata));

        $this->assertSame('seo_surface', $metadata->getTableName());
        $this->assertSame(['uniq_seo_surface_key_locale', 0], array_keys($metadata->table['uniqueConstraints'] ?? []));
        $this->assertSame(['idx_other'], array_keys($metadata->table['indexes'] ?? []), 'names without nowo_seo_ are kept');

        $both = new ClassMetadata(SeoSurface::class);
        $both->setPrimaryTable(['name' => 'nowo_seo_surface', 'uniqueConstraints' => ['uniq_nowo_seo_surface_key_locale' => ['columns' => ['locale']]]]);
        (new TablePrefixListener('app_', 'seo_'))->loadClassMetadata($this->event($both));
        $this->assertSame('app_seo_surface', $both->getTableName());
        $this->assertSame(['app_uniq_seo_surface_key_locale'], array_keys($both->table['uniqueConstraints'] ?? []));
    }

    public function testDefaultsLeaveNamesUnchanged(): void
    {
        $metadata = new ClassMetadata(SeoSurface::class);
        $metadata->setPrimaryTable(['name' => 'nowo_seo_surface']);

        (new TablePrefixListener('', TablePrefixListener::DEFAULT_TABLE_NAME_PREFIX))->loadClassMetadata($this->event($metadata));

        $this->assertSame('nowo_seo_surface', $metadata->getTableName());
    }

    /**
     * @param class-string $class
     *
     * @return ClassMetadata<object>
     */
    private function metadata(string $class): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        $metadata->setPrimaryTable([
            'name'    => 'nowo_seo_surface',
            'indexes' => [
                'idx_seo_a' => ['columns' => ['ip_address', 'username']],
                'idx_seo_b' => ['columns' => ['created_at']],
            ],
        ]);

        return $metadata;
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function event(ClassMetadata $metadata): LoadClassMetadataEventArgs
    {
        return new LoadClassMetadataEventArgs($metadata, $this->createStub(EntityManagerInterface::class));
    }
}
