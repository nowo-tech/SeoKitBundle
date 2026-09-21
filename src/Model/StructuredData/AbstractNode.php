<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model\StructuredData;

use function in_array;
use function is_array;
use function is_string;

/**
 * Shared cleanup for schema.org nodes: an empty property is worse than an absent one, because
 * validators report it as a malformed value.
 */
abstract class AbstractNode implements StructuredDataNode
{
    /**
     * @param array<mixed> $properties
     *
     * @return array<mixed>
     */
    final protected static function prune(array $properties): array
    {
        $wasList = array_is_list($properties);
        $pruned  = [];
        foreach ($properties as $key => $value) {
            if (is_array($value)) {
                $value = self::prune($value);
            }

            if (in_array($value, [null, '', []], true)) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            $pruned[$key] = $value;
        }

        // Lists must stay lists: a gap in the keys makes json_encode emit an object.
        return $wasList ? array_values($pruned) : $pruned;
    }
}
