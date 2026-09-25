<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Service\SeoRuntime;
use PHPUnit\Framework\TestCase;

final class SeoRuntimeTest extends TestCase
{
    public function testResetClearsOverridesAndVariables(): void
    {
        $runtime = new SeoRuntime();
        $runtime->set(['title' => 'A', 'noindex' => true]);
        $runtime->set(['title' => 'B']);
        $runtime->setVariables(['slug' => 'a']);

        self::assertSame(['title' => 'B', 'noindex' => true], $runtime->getOverrides());

        $runtime->reset();

        self::assertSame([], $runtime->getOverrides());
        self::assertSame([], $runtime->getVariables());
    }
}
