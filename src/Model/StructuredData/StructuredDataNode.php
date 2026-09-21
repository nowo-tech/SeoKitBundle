<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model\StructuredData;

/**
 * One schema.org node of a page.
 *
 * Structured data is built from typed nodes instead of hand-written JSON so a missing comma or an
 * unescaped quote cannot silently invalidate the whole graph.
 */
interface StructuredDataNode
{
    /**
     * The node as a plain array, `@var` included and empty values already dropped.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
