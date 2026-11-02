<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use InvalidArgumentException;

use function is_string;
use function max;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr_replace;

/**
 * Applies the configurable naming of the SeoKit tables and their named unique constraints (REQ-ORM-002).
 *
 * `nowo_seo_kit.doctrine.table_name_prefix` replaces the `nowo_seo_` part of every table and unique-constraint name
 * (`seo_` gives seo_surface and uniq_seo_surface_key_locale). The optional `nowo_seo_kit.doctrine.table_prefix` is
 * then prepended to the result (`app_` gives app_nowo_seo_surface and app_uniq_nowo_seo_surface_key_locale), so names
 * stay unique on databases with schema-wide index names. Only entities of this bundle are touched. Registered only
 * when either option differs from its default, so default names are left exactly as mapped.
 */
final readonly class TablePrefixListener
{
    /** The name prefix every table (and every derived index / unique-constraint name) of the bundle is mapped with. */
    public const DEFAULT_TABLE_NAME_PREFIX = 'nowo_seo_';

    /** Longest identifier PostgreSQL keeps (MySQL allows 64): every generated name must fit. */
    public const MAX_IDENTIFIER_LENGTH = 63;

    /** Allowed prefix: lowercase letter first, then lowercase letters, digits and underscores (no trailing newline). */
    public const PREFIX_PATTERN = '/^[a-z][a-z0-9_]*\z/';

    /** Longest mapped name once `DEFAULT_TABLE_NAME_PREFIX` is removed: uniq_nowo_seo_site_settings_translation_locale without `nowo_seo_`. */
    private const LONGEST_NAME_REST = 37;

    /** Longest named index without `DEFAULT_TABLE_NAME_PREFIX` (only `table_prefix` lengthens it): every named unique contains `nowo_seo_`. */
    private const LONGEST_FIXED_NAME = 0;

    private const ENTITY_NAMESPACE = 'Nowo\\SeoKitBundle\\Entity\\';

    /**
     * Builds the listener from the configured prefixes.
     *
     * @param string $tablePrefix Optional prefix prepended to every name (empty for none)
     * @param string $tableNamePrefix Replaces `nowo_seo_` in every table, index and unique-constraint name
     */
    public function __construct(
        private string $tablePrefix,
        private string $tableNamePrefix = self::DEFAULT_TABLE_NAME_PREFIX,
    ) {
        self::validate($this->tablePrefix, $this->tableNamePrefix);
    }

    /**
     * Rejects prefixes that are not lowercase identifiers or that make a generated name longer than 63 characters.
     * Run by the extension for literal values and by the constructor for resolved `%env()%` values.
     *
     * @param string $tablePrefix Optional prefix prepended to every name (empty for none)
     * @param string $tableNamePrefix Replacement of `DEFAULT_TABLE_NAME_PREFIX`
     *
     * @throws InvalidArgumentException when a prefix is malformed or a name would be too long
     */
    public static function validate(string $tablePrefix, string $tableNamePrefix): void
    {
        if (preg_match(self::PREFIX_PATTERN, $tableNamePrefix) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid doctrine.table_name_prefix "%s": use a lowercase letter, then lowercase letters, digits and underscores.', $tableNamePrefix));
        }
        if ($tablePrefix !== '' && preg_match(self::PREFIX_PATTERN, $tablePrefix) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid doctrine.table_prefix "%s": use a lowercase letter, then lowercase letters, digits and underscores (or empty).', $tablePrefix));
        }
        $longest = max(strlen($tablePrefix) + strlen($tableNamePrefix) + self::LONGEST_NAME_REST, strlen($tablePrefix) + self::LONGEST_FIXED_NAME);
        if ($longest > self::MAX_IDENTIFIER_LENGTH) {
            throw new InvalidArgumentException(sprintf('doctrine.table_prefix "%s" + doctrine.table_name_prefix "%s" make a table, index or sequence name of %d characters; the limit is %d.', $tablePrefix, $tableNamePrefix, $longest, self::MAX_IDENTIFIER_LENGTH));
        }
    }

    /**
     * Rewrites the table, index and unique-constraint names of a bundle entity while its metadata loads.
     *
     * @param LoadClassMetadataEventArgs $event Doctrine metadata event
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();
        if (($this->tablePrefix === '' && $this->tableNamePrefix === self::DEFAULT_TABLE_NAME_PREFIX)
            || !str_starts_with($metadata->getName(), self::ENTITY_NAMESPACE)) {
            return;
        }

        $table = ['name' => $this->rename($metadata->getTableName())];
        foreach (['indexes', 'uniqueConstraints'] as $key) {
            $definitions = $metadata->table[$key] ?? [];
            if ($definitions === []) {
                continue;
            }

            $renamed = [];
            foreach ($definitions as $name => $definition) {
                $renamed[is_string($name) ? $this->rename($name) : $name] = $definition;
            }
            $table[$key] = $renamed;
        }

        $metadata->setPrimaryTable($table);
    }

    /**
     * Swaps the first `nowo_seo_` of a mapped name for the configured name prefix, then prepends the optional prefix.
     *
     * @param string $name Table, index or unique-constraint name as mapped on the entity
     */
    private function rename(string $name): string
    {
        $position = strpos($name, self::DEFAULT_TABLE_NAME_PREFIX);
        if ($position !== false) {
            $name = substr_replace($name, $this->tableNamePrefix, $position, strlen(self::DEFAULT_TABLE_NAME_PREFIX));
        }

        return $this->tablePrefix . $name;
    }
}
