<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Model\StructuredData;

use Nowo\SeoKitBundle\Model\StructuredData\BreadcrumbListNode;
use Nowo\SeoKitBundle\Model\StructuredData\OrganizationNode;
use Nowo\SeoKitBundle\Model\StructuredData\StructuredDataGraph;
use Nowo\SeoKitBundle\Model\StructuredData\WebSiteNode;
use PHPUnit\Framework\TestCase;

use const JSON_THROW_ON_ERROR;

final class StructuredDataGraphTest extends TestCase
{
    public function testAnEmptyGraphIsReportedAsEmpty(): void
    {
        self::assertTrue((new StructuredDataGraph())->isEmpty());
    }

    public function testASingleNodeIsEmittedWithoutAGraphWrapper(): void
    {
        $graph = new StructuredDataGraph(new OrganizationNode('Nowo', 'https://nowo.tech'));

        $decoded = json_decode($graph->toJson(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('https://schema.org', $decoded['@context']);
        self::assertSame('Organization', $decoded['@type']);
        self::assertArrayNotHasKey('@graph', $decoded);
    }

    public function testSeveralNodesShareOneGraph(): void
    {
        $graph = new StructuredDataGraph(
            new OrganizationNode('Nowo', 'https://nowo.tech'),
            new WebSiteNode('Nowo', 'https://nowo.tech', 'es'),
        );

        $decoded = json_decode($graph->toJson(), true, flags: JSON_THROW_ON_ERROR);

        self::assertCount(2, $decoded['@graph']);
        self::assertSame(['Organization', 'WebSite'], array_column($decoded['@graph'], '@type'));
    }

    public function testWithReturnsANewGraphAndLeavesTheOriginalAlone(): void
    {
        $base     = new StructuredDataGraph(new OrganizationNode('Nowo', 'https://nowo.tech'));
        $extended = $base->with(new WebSiteNode('Nowo', 'https://nowo.tech', 'es'));

        self::assertArrayNotHasKey('@graph', $base->toArray());
        self::assertSame('Organization', $base->toArray()['@type']);
        self::assertCount(2, $extended->toArray()['@graph']);
    }

    public function testEmptyPropertiesAreDroppedRatherThanEmittedBlank(): void
    {
        $node = new OrganizationNode('Nowo', 'https://nowo.tech', logo: '', description: '   ', sameAs: []);

        $decoded = $node->toArray();

        self::assertArrayNotHasKey('logo', $decoded);
        self::assertArrayNotHasKey('description', $decoded);
        self::assertArrayNotHasKey('sameAs', $decoded);
        self::assertArrayNotHasKey('contactPoint', $decoded);
    }

    public function testMarkupInContentCannotCloseTheScriptTag(): void
    {
        $graph = new StructuredDataGraph(
            new OrganizationNode('</script><script>alert("xss")</script>', 'https://nowo.tech'),
        );

        $json = $graph->toJson();

        self::assertStringNotContainsString('</script>', $json);
        self::assertStringNotContainsString('<', $json);
        self::assertStringNotContainsString('"xss"', $json);
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('</script><script>alert("xss")</script>', $decoded['name']);
    }

    public function testAccentsSurviveWithoutEscapeSequences(): void
    {
        $graph = new StructuredDataGraph(new OrganizationNode('Ahorro y gestión', 'https://nowo.tech'));

        self::assertStringContainsString('Ahorro y gestión', $graph->toJson());
    }

    public function testBreadcrumbPositionsAreOneBasedAndStayAList(): void
    {
        $node = new BreadcrumbListNode([
            ['name' => 'Inicio', 'url' => 'https://nowo.tech'],
            ['name' => 'Productos', 'url' => 'https://nowo.tech/productos'],
        ]);

        $decoded = $node->toArray();

        self::assertSame([1, 2], array_column($decoded['itemListElement'], 'position'));
        self::assertTrue(array_is_list($decoded['itemListElement']));
    }

    public function testWebSiteReferencesItsPublisherById(): void
    {
        $organisation = new OrganizationNode('Nowo', 'https://nowo.tech');
        $site         = new WebSiteNode('Nowo', 'https://nowo.tech', 'es', $organisation->toArray()['@id']);

        self::assertSame(['@id' => 'https://nowo.tech#organization'], $site->toArray()['publisher']);
    }
}
