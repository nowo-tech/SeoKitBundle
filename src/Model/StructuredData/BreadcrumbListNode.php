<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model\StructuredData;

use Override;

/**
 * The trail from the home page to the current page, so search results can show the site hierarchy
 * instead of a bare URL.
 */
final class BreadcrumbListNode extends AbstractNode
{
    /**
     * @param list<array{name: string, url: string}> $items ordered from the home page to the current page
     */
    public function __construct(private readonly array $items)
    {
    }

    #[Override]
    public function toArray(): array
    {
        $elements = [];
        foreach ($this->items as $position => $item) {
            $elements[] = [
                '@type'    => 'ListItem',
                'position' => $position + 1,
                'name'     => $item['name'],
                'item'     => $item['url'],
            ];
        }

        return self::prune([
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $elements,
        ]);
    }
}
