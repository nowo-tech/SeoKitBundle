<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\EventSubscriber;

use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\EventSubscriber\SeoRuntimeClearSubscriber;
use Nowo\SeoKitBundle\Model\PageHeadDefaults;
use Nowo\SeoKitBundle\Model\PageHeadInput;
use Nowo\SeoKitBundle\Model\StructuredData\StructuredDataGraph;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Nowo\SeoKitBundle\Service\AbsoluteUrlBuilder;
use Nowo\SeoKitBundle\Service\HreflangSetBuilder;
use Nowo\SeoKitBundle\Service\PageHeadContext;
use Nowo\SeoKitBundle\Service\PageHeadDefaultsProviderInterface;
use Nowo\SeoKitBundle\Service\PageHeadResolver;
use Nowo\SeoKitBundle\Service\PageHeadSiteGraphProviderInterface;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProvider;
use Nowo\SeoKitBundle\Service\SeoRuntime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class SeoRuntimeClearSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        self::assertSame(
            [
                KernelEvents::REQUEST   => ['onRequest', 4096],
                KernelEvents::TERMINATE => 'onTerminate',
            ],
            SeoRuntimeClearSubscriber::getSubscribedEvents(),
        );
    }

    public function testClearsRuntimeOnTerminate(): void
    {
        $runtime = new SeoRuntime();
        $runtime->set(['title' => 'X']);
        $runtime->setVariables(['slug' => 'y']);
        self::assertNotSame([], $runtime->getOverrides());

        $subscriber = new SeoRuntimeClearSubscriber($runtime);
        $kernel     = $this->createMock(HttpKernelInterface::class);
        $subscriber->onTerminate(new TerminateEvent($kernel, Request::create('/'), new Response()));

        self::assertSame([], $runtime->getOverrides());
        self::assertSame([], $runtime->getVariables());
    }

    public function testConsecutiveMainRequestsDoNotLeakStateWithoutKernelReset(): void
    {
        $runtime = new SeoRuntime();
        $context = $this->pageHeadContext();

        $first = new SeoSiteSettings();
        $first->setSiteName('Before admin edit');
        $second = new SeoSiteSettings();
        $second->setSiteName('After admin edit');
        $repository = $this->createMock(SeoSiteSettingsRepository::class);
        $repository->expects(self::exactly(2))->method('getOrCreate')->willReturnOnConsecutiveCalls($first, $second);
        $siteConfig = new SeoSiteConfigProvider($repository);

        $subscriber = new SeoRuntimeClearSubscriber($runtime, $context, $siteConfig);

        $subscriber->onRequest($this->requestEvent(HttpKernelInterface::MAIN_REQUEST));
        $runtime->set(['title' => 'Private page of user A', 'noindex' => true]);
        $runtime->setVariables(['slug' => 'user-a']);
        $context->describe(new PageHeadInput(path: '/account/a', locale: 'es', templateTitle: 'User A'));
        self::assertSame('User A', $context->pageHead()->title);
        self::assertSame('Before admin edit', $siteConfig->get()->siteName);
        self::assertSame('Before admin edit', $siteConfig->get()->siteName);

        $subscriber->onRequest($this->requestEvent(HttpKernelInterface::MAIN_REQUEST));

        self::assertSame([], $runtime->getOverrides());
        self::assertSame([], $runtime->getVariables());
        self::assertFalse($context->isDescribed());
        self::assertSame('After admin edit', $siteConfig->get()->siteName);
    }

    public function testSubRequestsKeepTheStateOfTheirMainRequest(): void
    {
        $runtime = new SeoRuntime();
        $context = $this->pageHeadContext();
        $runtime->set(['title' => 'Main page']);
        $context->describe(new PageHeadInput(path: '/', locale: 'es'));

        (new SeoRuntimeClearSubscriber($runtime, $context))->onRequest($this->requestEvent(HttpKernelInterface::SUB_REQUEST));

        self::assertSame(['title' => 'Main page'], $runtime->getOverrides());
        self::assertTrue($context->isDescribed());
    }

    public function testMainRequestWithoutOptionalServicesClearsRuntime(): void
    {
        $runtime = new SeoRuntime();
        $runtime->set(['title' => 'Previous']);

        (new SeoRuntimeClearSubscriber($runtime))->onRequest($this->requestEvent(HttpKernelInterface::MAIN_REQUEST));

        self::assertSame([], $runtime->getOverrides());
    }

    private function requestEvent(int $type): RequestEvent
    {
        return new RequestEvent($this->createMock(HttpKernelInterface::class), Request::create('/'), $type);
    }

    private function pageHeadContext(): PageHeadContext
    {
        $urls = new AbsoluteUrlBuilder('https://nowo.tech');

        $defaults = self::createStub(PageHeadDefaultsProviderInterface::class);
        $defaults->method('forLocale')->willReturn(new PageHeadDefaults(
            siteName: 'Nowo',
            title: 'Nowo',
            description: 'Platform description.',
            robots: 'index,follow',
        ));

        $structuredData = self::createStub(PageHeadSiteGraphProviderInterface::class);
        $structuredData->method('forLocale')->willReturn(new StructuredDataGraph());

        return new PageHeadContext(new PageHeadResolver(
            $urls,
            new HreflangSetBuilder($urls, ['es', 'en'], 'es'),
            $defaults,
            $structuredData,
            null,
            ['es' => 'ES', 'en' => 'GB'],
        ));
    }
}
