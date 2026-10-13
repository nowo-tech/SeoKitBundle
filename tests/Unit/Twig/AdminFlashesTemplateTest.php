<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

use function dirname;
use function file_get_contents;

/**
 * Admin controllers add flash messages after saving: the bundled admin templates must render them.
 */
final class AdminFlashesTemplateTest extends TestCase
{
    public function testFlashesPartialRendersTranslatedMessages(): void
    {
        $html = $this->twig()->render('@NowoSeoKitBundle/admin/_flashes.html.twig', [
            'app' => ['flashes' => ['success' => ['nowo_seo_kit.flash.surface_saved']]],
        ]);

        self::assertStringContainsString('nowo-seo-kit-flash--success', $html);
        self::assertStringContainsString('[NowoSeoKitBundle]nowo_seo_kit.flash.surface_saved', $html);
    }

    public function testFlashesPartialIsSilentWithoutApp(): void
    {
        self::assertSame('', trim($this->twig()->render('@NowoSeoKitBundle/admin/_flashes.html.twig')));
    }

    public function testAdminTemplatesIncludeFlashes(): void
    {
        foreach (['settings', 'surfaces_index', 'surfaces_form'] as $template) {
            $source = (string) file_get_contents($this->viewsDir() . '/admin/' . $template . '.html.twig');
            self::assertStringContainsString("{{ include('@NowoSeoKitBundle/admin/_flashes.html.twig') }}", $source, $template);
        }
    }

    private function twig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath($this->viewsDir(), 'NowoSeoKitBundle');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addFilter(new TwigFilter('trans', static fn (string $message, array $params = [], ?string $domain = null): string => '[' . $domain . ']' . $message));

        return $twig;
    }

    private function viewsDir(): string
    {
        return dirname(__DIR__, 3) . '/src/Resources/views';
    }
}
