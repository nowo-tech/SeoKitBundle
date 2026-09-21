<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model\StructuredData;

use JsonException;

use function count;

use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The JSON-LD graph of a page, ready to be embedded in a `<script type="application/ld+json">`.
 */
final readonly class StructuredDataGraph
{
    /** @var list<StructuredDataNode> */
    private array $nodes;

    public function __construct(StructuredDataNode ...$nodes)
    {
        $this->nodes = array_values($nodes);
    }

    public function with(StructuredDataNode ...$nodes): self
    {
        return new self(...$this->nodes, ...$nodes);
    }

    public function isEmpty(): bool
    {
        return $this->nodes === [];
    }

    /**
     * Serialised with the escaping flags that keep the payload safe inside an HTML document:
     * `<`, `>`, `&` and quotes are escaped, so page content cannot close the script tag.
     *
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode(
            $this->toArray(),
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT,
        );
    }

    /**
     * A single node is emitted on its own; several share one `@graph`, which keeps the document
     * to one script tag whatever the page type.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if (count($this->nodes) === 1) {
            return ['@context' => 'https://schema.org'] + $this->nodes[0]->toArray();
        }

        return [
            '@context' => 'https://schema.org',
            '@graph'   => array_map(static fn (StructuredDataNode $node): array => $node->toArray(), $this->nodes),
        ];
    }
}
