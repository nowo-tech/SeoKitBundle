<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\EventSubscriber;

use Nowo\SeoKitBundle\EventSubscriber\XRobotsTagSubscriber;
use Nowo\SeoKitBundle\Service\SeoMetadataResolver;
use Nowo\SeoKitBundle\Service\SeoPathBuilder;
use Nowo\SeoKitBundle\Service\SeoRuntime;
use Nowo\SeoKitBundle\Service\SeoTemplateRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class XRobotsTagSubscriberTest extends TestCase
{
    public function testItListensToResponses(): void
    {
        self::assertSame([KernelEvents::RESPONSE => ['onResponse', -16]], XRobotsTagSubscriber::getSubscribedEvents());
    }

    public function testEnabledItSendsThePagesRobots(): void
    {
        $subscriber = new XRobotsTagSubscriber($this->resolver(), true);

        self::assertSame('noindex,nofollow', $this->header($subscriber, 'app_dashboard'));
        self::assertSame('index,follow', $this->header($subscriber, 'app_login'), 'a page layer opens it');
    }

    public function testItKeepsAnExistingHeaderSkipsSubRequestsAndIsOffByDefault(): void
    {
        $resolver   = $this->resolver();
        $subscriber = new XRobotsTagSubscriber($resolver, true);

        self::assertSame('none', $this->header($subscriber, 'app_dashboard', new Response(headers: ['X-Robots-Tag' => 'none'])));
        self::assertNull($this->header($subscriber, 'app_dashboard', null, HttpKernelInterface::SUB_REQUEST));
        self::assertNull($this->header(new XRobotsTagSubscriber($resolver), 'app_dashboard'));
    }

    public function testWithTheKitOffThePageIsNotIndexed(): void
    {
        $subscriber = new XRobotsTagSubscriber($this->resolver(['enabled' => false]), true);

        self::assertSame('noindex,nofollow', $this->header($subscriber, 'app_login'));
    }

    private function header(XRobotsTagSubscriber $subscriber, string $route, ?Response $response = null, int $type = HttpKernelInterface::MAIN_REQUEST): ?string
    {
        $request = Request::create('/x');
        $request->attributes->set('_route', $route);
        $response ??= new Response();
        $subscriber->onResponse(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, $type, $response));

        return $response->headers->get('X-Robots-Tag');
    }

    /**
     * @param array<string, mixed> $override
     */
    private function resolver(array $override = []): SeoMetadataResolver
    {
        $config = [
            'enabled'        => true,
            'default_locale' => 'en',
            'locales'        => ['en'],
            'base_url'       => 'https://example.com',
            'defaults'       => ['site_name' => '', 'title_template' => '{title}', 'robots' => 'noindex,nofollow'],
            'pages'          => ['app_login' => ['robots' => 'index,follow']],
            ...$override,
        ];
        $routes = new RouteCollection();
        $routes->add('app_dashboard', new Route('/x'));
        $routes->add('app_login', new Route('/login'));

        return new SeoMetadataResolver($config, new RequestStack(), new SeoRuntime(), new SeoTemplateRenderer(), new SeoPathBuilder($config), new UrlGenerator($routes, new RequestContext()));
    }
}
