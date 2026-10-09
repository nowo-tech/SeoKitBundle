<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function dirname;

/**
 * The bundled seo/head.html.twig JSON-LD fallbacks must not let editor text close the script block.
 */
final class HeadTemplateJsonLdTest extends TestCase
{
    public function testDocumentFallbackEscapesScriptBreakout(): void
    {
        $html = $this->render([
            'enabled'  => true,
            'json'     => null,
            'document' => ['@context' => 'https://schema.org', 'name' => '</script><script>alert(1)</script> & "q" \'a\''],
            'graph'    => [],
        ]);

        self::assertStringNotContainsString('</script><script>', $html);
        self::assertStringContainsString('\\u003C/script\\u003E', $html);
        self::assertStringContainsString('\\u0026', $html);
        self::assertStringContainsString('https://schema.org', $html, 'Slashes stay unescaped.');
        self::assertSame(1, substr_count($html, '</script>'));
    }

    public function testGraphFallbackEscapesScriptBreakout(): void
    {
        $html = $this->render([
            'enabled'  => true,
            'json'     => null,
            'document' => null,
            'graph'    => [['@type' => 'Organization', 'name' => 'Ñoño </script>']],
        ]);

        self::assertStringContainsString('"@graph"', $html);
        self::assertStringContainsString('Ñoño \\u003C/script\\u003E', $html, 'Unicode stays unescaped.');
        self::assertSame(1, substr_count($html, '</script>'));
    }

    /**
     * @param array{enabled: bool, json: ?string, document: array<string, mixed>|null, graph: list<array<string, mixed>>} $jsonLd
     */
    private function render(array $jsonLd): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 3) . '/src/Resources/views'));

        return $twig->render('seo/head.html.twig', [
            'seo_include_title' => false,
            'seo'               => ['jsonLd' => $jsonLd, 'verification' => ['google' => null, 'bing' => null]],
        ]);
    }
}
