<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Translation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

use function array_diff;
use function array_values;
use function basename;
use function dirname;
use function glob;
use function is_array;
use function preg_match;
use function sort;

/**
 * Every shipped locale must define exactly the keys of the English catalogue.
 */
final class TranslationKeyParityTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function localeFiles(): iterable
    {
        foreach (glob(self::dir() . '/NowoSeoKitBundle.*.yaml') ?: [] as $file) {
            if (preg_match('/\.en\.yaml$/', $file) === 1) {
                continue;
            }

            yield basename($file) => [$file];
        }
    }

    #[DataProvider('localeFiles')]
    public function testLocaleHasSameKeysAsEnglish(string $file): void
    {
        $reference = self::flatKeys(Yaml::parseFile(self::dir() . '/NowoSeoKitBundle.en.yaml'));
        $keys      = self::flatKeys(Yaml::parseFile($file));

        self::assertSame([], array_values(array_diff($reference, $keys)), 'Missing keys in ' . basename($file));
        self::assertSame([], array_values(array_diff($keys, $reference)), 'Extra keys in ' . basename($file));
    }

    public function testAllExpectedLocalesAreShipped(): void
    {
        $locales = [];
        foreach (glob(self::dir() . '/NowoSeoKitBundle.*.yaml') ?: [] as $file) {
            if (preg_match('/\.([a-z_]+)\.yaml$/i', $file, $m) === 1) {
                $locales[] = $m[1];
            }
        }
        sort($locales);

        self::assertSame(['de', 'en', 'es', 'fr', 'it', 'nl', 'pt'], $locales);
    }

    /**
     * @return list<string>
     */
    private static function flatKeys(mixed $tree, string $prefix = ''): array
    {
        if (!is_array($tree)) {
            return [$prefix];
        }

        $keys = [];
        foreach ($tree as $key => $value) {
            foreach (self::flatKeys($value, $prefix === '' ? (string) $key : $prefix . '.' . $key) as $flat) {
                $keys[] = $flat;
            }
        }

        return $keys;
    }

    private static function dir(): string
    {
        return dirname(__DIR__, 3) . '/src/Resources/translations';
    }
}
