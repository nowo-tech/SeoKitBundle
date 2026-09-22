<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Entity\SeoSurface;

final readonly class SeoPencilCatalogFactory
{
    /**
     * @param array{title: string, description: string, robots?: string, canonical?: string} $inherited
     * @param array{
     *     title: string,
     *     preview: array{label: string, over: string, url: string, emptyTitle: string, emptyDescription: string},
     *     inherits_prefix: string,
     *     fields: array{
     *         meta_title: array{label: string},
     *         meta_description: array{label: string},
     *         meta_robots?: array{label: string, inherit: string, options: list<array{value: string, label: string}>},
     *         canonical?: array{label: string},
     *         open_graph_image?: array{label: string, help: string}
     *     }
     * } $labels
     *
     * @return array{title: string, preview: array<string, string>, fields: list<array<string, mixed>>}
     */
    public function build(?SeoSurface $surface, array $inherited, array $labels, bool $includeAdvanced = true): array
    {
        $inherits = $labels['inherits_prefix'];
        $fields = [
            $this->field('metaTitle', 'text', $labels['fields']['meta_title']['label'], $surface?->getMetaTitle(), $inherits.' '.$inherited['title']),
            $this->field('metaDescription', 'textarea', $labels['fields']['meta_description']['label'], $surface?->getMetaDescription(), $inherits.' '.$inherited['description']),
        ];

        if ($includeAdvanced && isset($labels['fields']['meta_robots'])) {
            $fields[] = [
                'key' => 'metaRobots',
                'kind' => 'choice',
                'label' => $labels['fields']['meta_robots']['label'],
                'help' => $inherits.' '.($inherited['robots'] ?? ''),
                'value' => $surface?->getMetaRobots() ?? '',
                'options' => $labels['fields']['meta_robots']['options'],
            ];
        }
        if ($includeAdvanced && isset($labels['fields']['canonical'])) {
            $fields[] = $this->field('canonicalOverride', 'text', $labels['fields']['canonical']['label'], $surface?->getCanonicalOverride(), $inherits.' '.($inherited['canonical'] ?? ''));
        }
        if ($includeAdvanced && isset($labels['fields']['open_graph_image'])) {
            $fields[] = $this->field('openGraphImage', 'text', $labels['fields']['open_graph_image']['label'], $surface?->getOpenGraphImage(), $labels['fields']['open_graph_image']['help']);
        }

        return ['title' => $labels['title'], 'preview' => $labels['preview'], 'fields' => $fields];
    }

    /** @return array{key: string, kind: string, label: string, help: string, value: string} */
    private function field(string $key, string $kind, string $label, ?string $value, string $help): array
    {
        return ['key' => $key, 'kind' => $kind, 'label' => $label, 'help' => $help, 'value' => $value ?? ''];
    }
}
