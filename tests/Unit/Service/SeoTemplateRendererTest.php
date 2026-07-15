<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Service\SeoTemplateRenderer;
use PHPUnit\Framework\TestCase;

final class SeoTemplateRendererTest extends TestCase
{
    private SeoTemplateRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new SeoTemplateRenderer();
    }

    public function testRenderNullReturnsNull(): void
    {
        $this->assertNull($this->renderer->render(null, []));
    }

    public function testRenderEmptyStringReturnsEmptyString(): void
    {
        $this->assertSame('', $this->renderer->render('', []));
    }

    public function testRenderReplacesPlaceholders(): void
    {
        $result = $this->renderer->render('{title}{separator}{site_name}', [
            'title' => 'Home',
            'separator' => ' | ',
            'site_name' => 'Demo',
        ]);

        $this->assertSame('Home | Demo', $result);
    }

    public function testRenderTreatsNullVariablesAsEmptyString(): void
    {
        $result = $this->renderer->render('{slug}', ['slug' => null]);

        $this->assertSame('', $result);
    }

    public function testRenderLeavesUnknownPlaceholdersUntouched(): void
    {
        $result = $this->renderer->render('Hello {unknown}', ['title' => 'X']);

        $this->assertSame('Hello {unknown}', $result);
    }
}
