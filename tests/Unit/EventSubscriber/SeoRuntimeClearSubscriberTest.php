<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\EventSubscriber;

use Nowo\SeoKitBundle\EventSubscriber\SeoRuntimeClearSubscriber;
use Nowo\SeoKitBundle\Service\SeoRuntime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SeoRuntimeClearSubscriberTest extends TestCase
{
    public function testClearsRuntimeOnTerminate(): void
    {
        $runtime = new SeoRuntime();
        $runtime->set(['title' => 'X']);
        $runtime->setVariables(['slug' => 'y']);
        self::assertNotSame([], $runtime->getOverrides());

        $subscriber = new SeoRuntimeClearSubscriber($runtime);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $subscriber->onTerminate(new TerminateEvent($kernel, Request::create('/'), new Response()));

        self::assertSame([], $runtime->getOverrides());
        self::assertSame([], $runtime->getVariables());
        self::assertArrayHasKey('kernel.terminate', SeoRuntimeClearSubscriber::getSubscribedEvents());
    }
}
