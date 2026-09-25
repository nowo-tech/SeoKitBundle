<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Service\SeoPencilCatalogFactory;
use PHPUnit\Framework\TestCase;

final class SeoPencilCatalogFactoryTest extends TestCase
{
    public function testBuildIncludesAdvancedFieldsWhenRequested(): void
    {
        $surface = new SeoSurface();
        $surface->setMetaTitle('Custom');
        $surface->setMetaDescription('Desc');
        $surface->setMetaRobots('noindex');
        $surface->setCanonicalOverride('https://nowo.tech/x');
        $surface->setOpenGraphImage('https://nowo.tech/og.png');

        $catalog = (new SeoPencilCatalogFactory())->build(
            $surface,
            ['title' => 'Inherited title', 'description' => 'Inherited desc', 'robots' => 'index', 'canonical' => 'https://nowo.tech/'],
            [
                'title'           => 'SEO',
                'preview'         => ['label' => 'P', 'over' => 'O', 'url' => '/', 'emptyTitle' => '', 'emptyDescription' => ''],
                'inherits_prefix' => 'Inherits',
                'fields'          => [
                    'meta_title'       => ['label' => 'Title'],
                    'meta_description' => ['label' => 'Description'],
                    'meta_robots'      => [
                        'label'   => 'Robots',
                        'inherit' => 'Inherit',
                        'options' => [['value' => '', 'label' => 'Inherit'], ['value' => 'noindex', 'label' => 'Noindex']],
                    ],
                    'canonical'        => ['label' => 'Canonical'],
                    'open_graph_image' => ['label' => 'OG', 'help' => 'OG help'],
                ],
            ],
            true,
        );

        self::assertSame('SEO', $catalog['title']);
        self::assertCount(5, $catalog['fields']);
        self::assertSame('Custom', $catalog['fields'][0]['value']);
        self::assertSame('metaRobots', $catalog['fields'][2]['key']);
        self::assertSame('noindex', $catalog['fields'][2]['value']);
        self::assertSame('OG help', $catalog['fields'][4]['help']);
    }

    public function testBuildSkipsAdvancedFieldsWhenDisabledOrMissingLabels(): void
    {
        $catalog = (new SeoPencilCatalogFactory())->build(
            null,
            ['title' => 'T', 'description' => 'D'],
            [
                'title'           => 'SEO',
                'preview'         => ['label' => 'P', 'over' => 'O', 'url' => '/', 'emptyTitle' => '', 'emptyDescription' => ''],
                'inherits_prefix' => 'Inherits',
                'fields'          => [
                    'meta_title'       => ['label' => 'Title'],
                    'meta_description' => ['label' => 'Description'],
                ],
            ],
            false,
        );

        self::assertCount(2, $catalog['fields']);
        self::assertSame('', $catalog['fields'][0]['value']);
        self::assertStringContainsString('Inherits T', $catalog['fields'][0]['help']);
    }
}
