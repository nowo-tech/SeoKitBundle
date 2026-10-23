<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Controller;

use Nowo\SeoKitBundle\Controller\Admin\SeoSiteSettingsController;
use Nowo\SeoKitBundle\Controller\Admin\SeoSurfaceController;
use Nowo\SeoKitBundle\Controller\Api\SeoSurfaceApiController;
use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Model\SeoSiteConfig;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditor;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditRules;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProviderInterface;
use Nowo\SeoKitBundle\Tests\Support\FormKitTestSupport;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validation;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

use const JSON_THROW_ON_ERROR;

final class SeoAdminControllersTest extends TestCase
{
    public function testSettingsControllerGetAndPost(): void
    {
        $settings = new SeoSiteSettings();
        $settings->setSiteName('Nowo')->setTitleTemplate('%page% · %site%');

        $repo = $this->createMock(SeoSiteSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);
        $repo->expects(self::once())->method('save')->with($settings);

        $config = $this->createMock(SeoSiteConfigProviderInterface::class);
        $config->expects(self::once())->method('refresh');

        $controller = new SeoSiteSettingsController($repo, $config, ['es', 'en'], 'en');
        $controller->setContainer($this->container());

        $getRequest = new Request();
        $this->requestStack()->push($getRequest);
        $get = $controller->edit($getRequest);
        self::assertSame(200, $get->getStatusCode());
        self::assertStringContainsString('settings-ok', (string) $get->getContent());
        $this->requestStack()->pop();

        $post = Request::create('/settings/seo', 'POST', [
            'nowo_seo_site' => [
                'siteName'                   => 'Nowo',
                'titleTemplate'              => '%page% · %site%',
                'indexable'                  => '1',
                'defaultRobots'              => 'index, follow',
                'socialProfilesText'         => '',
                'defaultTitle_en'            => '',
                'homeTitle_en'               => '',
                'defaultDescription_en'      => '',
                'organisationDescription_en' => '',
                'defaultTitle_es'            => '',
                'homeTitle_es'               => '',
                'defaultDescription_es'      => '',
                'organisationDescription_es' => '',
            ],
        ]);
        $post->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack()->push($post);

        $response = $controller->edit($post);
        self::assertSame(302, $response->getStatusCode());
    }

    public function testSurfacesIndexAndEditClearEmptyOverride(): void
    {
        $surface = new SeoSurface();
        $surface->setSurfaceKey('page:home')->setLocale('en');
        // Simulate persisted entity with id via reflection
        $id = (new ReflectionClass($surface))->getProperty('id');
        $id->setValue($surface, 7);

        $repo = $this->createMock(SeoSurfaceRepository::class);
        $repo->method('findOneByKeyAndLocale')->willReturn($surface);
        $repo->expects(self::once())->method('remove')->with($surface);

        $config = $this->createMock(SeoSiteConfigProviderInterface::class);
        $config->method('get')->willReturn($this->siteConfig());

        $controller = new SeoSurfaceController($repo, new SeoAuditor(new SeoAuditRules()), $config, ['en'], 'en');
        $controller->setContainer($this->container());

        $indexReq = new Request(['locale' => 'en']);
        $this->requestStack()->push($indexReq);
        $index = $controller->index($indexReq);
        self::assertSame(200, $index->getStatusCode());
        $this->requestStack()->pop();

        $post = Request::create('/admin/seo/surfaces/en/edit/page%3Ahome', 'POST', [
            'nowo_seo_surface' => [
                'metaTitle'         => '',
                'metaDescription'   => '',
                'metaRobots'        => '',
                'canonicalOverride' => '',
                'openGraphImage'    => '',
            ],
        ]);
        $post->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack()->push($post);

        $response = $controller->edit('en', 'page%3Ahome', $post);
        self::assertSame(302, $response->getStatusCode());
    }

    public function testSurfaceEditRejectsUnknownLocale(): void
    {
        $surface = new SeoSurface();
        $surface->setSurfaceKey('page:home')->setLocale('en');

        $repo = $this->createMock(SeoSurfaceRepository::class);
        $repo->expects(self::never())->method('findOneByKeyAndLocale');

        $controller = new SeoSurfaceController($repo, new SeoAuditor(new SeoAuditRules()), null, ['en'], 'en');
        $controller->setContainer($this->container());

        $this->expectException(NotFoundHttpException::class);
        $controller->edit('xx', 'page:home', new Request());
    }

    public function testSurfaceEditSavesPayload(): void
    {
        $surface = new SeoSurface();
        $surface->setSurfaceKey('page:home')->setLocale('en');

        $repo = $this->createMock(SeoSurfaceRepository::class);
        $repo->method('findOneByKeyAndLocale')->willReturn(null);
        $repo->expects(self::once())->method('save')->with(self::callback(static fn (SeoSurface $saved): bool => $saved->getSurfaceKey() === 'page:home' && $saved->getLocale() === 'en' && $saved->getMetaTitle() === 'Home'));

        $controller = new SeoSurfaceController($repo, new SeoAuditor(new SeoAuditRules()), null, ['en'], 'en');
        $controller->setContainer($this->container());

        $post = Request::create('/', 'POST', [
            'nowo_seo_surface' => [
                'metaTitle'         => 'Home',
                'metaDescription'   => 'Desc',
                'metaRobots'        => 'index, follow',
                'canonicalOverride' => '/home',
                'openGraphImage'    => '/og.png',
            ],
        ]);
        $post->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack()->push($post);

        self::assertSame(302, $controller->edit('en', 'page:home', $post)->getStatusCode());
    }

    public function testSurfaceEditGetRendersForm(): void
    {
        $surface = new SeoSurface();
        $surface->setSurfaceKey('page:home')->setLocale('en');

        $repo = $this->createMock(SeoSurfaceRepository::class);
        $repo->method('findOneByKeyAndLocale')->willReturn(null);
        // Opening the form must not create (persist + flush) an empty override row.
        $repo->expects(self::never())->method('getOrCreate');
        $repo->expects(self::never())->method('save');
        $repo->expects(self::never())->method('remove');

        $controller = new SeoSurfaceController($repo, new SeoAuditor(new SeoAuditRules()), null, ['es', 'en'], 'en');
        $controller->setContainer($this->container());

        $get = Request::create('/admin/seo/surfaces/en/edit/page:home', 'GET');
        $this->requestStack()->push($get);
        $response = $controller->edit('en', 'page:home', $get);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('surfaces-form', (string) $response->getContent());
    }

    public function testApiRejectsFormEncodedPost(): void
    {
        $repo = $this->createMock(SeoSurfaceRepository::class);
        $repo->expects(self::never())->method('findOneByKeyAndLocale');
        $repo->expects(self::never())->method('save');

        $controller = new SeoSurfaceApiController($repo);
        $controller->setContainer($this->container());

        // Cross-site HTML form POST (no CSRF token possible): must be refused.
        $request = Request::create('/_nowo/seo/surfaces/page:home/en', 'POST', [
            'meta_title'         => 'From form',
            'canonical_override' => '/home',
        ]);

        $response = $controller->post('page:home', 'en', $request);
        self::assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $response->getStatusCode());

        // text/plain is a "simple" CORS request too: refused even when the body is JSON.
        $plain = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'text/plain'], '{"meta_title":"x"}');
        self::assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $controller->post('page:home', 'en', $plain)->getStatusCode());
    }

    public function testApiRejectsNonObjectJson(): void
    {
        $repo = $this->createMock(SeoSurfaceRepository::class);
        $repo->expects(self::never())->method('save');

        $controller = $this->apiController($repo);

        $request = Request::create('/', 'POST', [], [], [], ['HTTP_X_CSRF_TOKEN' => 'tok', 'CONTENT_TYPE' => 'application/json'], 'null');
        self::assertSame(Response::HTTP_BAD_REQUEST, $controller->post('page:home', 'en', $request)->getStatusCode());
    }

    public function testApiAcceptsJsonWithCsrfHeaderWhenFormCsrfIsEnabled(): void
    {
        $repo = $this->createMock(SeoSurfaceRepository::class);
        $repo->method('findOneByKeyAndLocale')->willReturn(null);
        $repo->expects(self::once())->method('save');

        $controller = $this->apiController($repo, formCsrf: true);

        $request  = Request::create('/', 'POST', [], [], [], ['HTTP_X_CSRF_TOKEN' => 'tok', 'CONTENT_TYPE' => 'application/json; charset=UTF-8'], json_encode(['meta_title' => 'Home'], JSON_THROW_ON_ERROR));
        $response = $controller->post('page:home', 'en', $request);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testApiRefusesMissingOrInvalidCsrfHeader(): void
    {
        $repo = $this->createMock(SeoSurfaceRepository::class);
        $repo->expects(self::never())->method('save');

        $controller = $this->apiController($repo);
        $body       = json_encode(['meta_title' => 'Home'], JSON_THROW_ON_ERROR);

        $missing = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
        self::assertSame(Response::HTTP_FORBIDDEN, $controller->post('page:home', 'en', $missing)->getStatusCode());

        $invalid = Request::create('/', 'POST', [], [], [], ['HTTP_X_CSRF_TOKEN' => 'forged', 'CONTENT_TYPE' => 'application/json'], $body);
        self::assertSame(Response::HTTP_FORBIDDEN, $controller->post('page:home', 'en', $invalid)->getStatusCode());
    }

    public function testApiCsrfFailsClosedWithoutTokenManager(): void
    {
        $repo = $this->createMock(SeoSurfaceRepository::class);
        $repo->expects(self::never())->method('save');

        $controller = new SeoSurfaceApiController($repo);
        $controller->setContainer($this->container());

        $request = Request::create('/', 'POST', [], [], [], ['HTTP_X_CSRF_TOKEN' => 'tok', 'CONTENT_TYPE' => 'application/json'], json_encode(['meta_title' => 'Home'], JSON_THROW_ON_ERROR));
        self::assertSame(Response::HTTP_FORBIDDEN, $controller->post('page:home', 'en', $request)->getStatusCode());
        self::assertNull(json_decode((string) $controller->get('page:home', 'en')->getContent(), true)['csrf_token']);
    }

    public function testApiCsrfCanBeDisabledForTokenFirewalls(): void
    {
        $repo = $this->createMock(SeoSurfaceRepository::class);
        $repo->method('findOneByKeyAndLocale')->willReturn(null);
        $repo->expects(self::once())->method('save');

        $controller = new SeoSurfaceApiController($repo, 'ROLE_ADMIN', null, false);
        $controller->setContainer($this->container());

        $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['meta_title' => 'Home'], JSON_THROW_ON_ERROR));
        self::assertSame(200, $controller->post('page:home', 'en', $request)->getStatusCode());
        self::assertNull(json_decode((string) $controller->get('page:home', 'en')->getContent(), true)['csrf_token']);
    }

    public function testApiGetPostValidationSaveAndClear(): void
    {
        $repo = $this->createMock(SeoSurfaceRepository::class);
        $repo->method('findOneByKeyAndLocale')->willReturn(null);
        $repo->expects(self::once())->method('save')->with(self::isInstanceOf(SeoSurface::class));

        $controller = $this->apiController($repo);

        $get = $controller->get('page:home', 'en');
        self::assertSame(200, $get->getStatusCode());
        $getContent = $get->getContent();
        self::assertNotFalse($getContent);
        self::assertSame('page:home', json_decode($getContent, true)['surface_key']);
        self::assertSame('tok', json_decode($getContent, true)['csrf_token']);

        $invalid = Request::create('/', 'POST', [], [], [], ['HTTP_X_CSRF_TOKEN' => 'tok', 'CONTENT_TYPE' => 'application/json'], json_encode([
            'meta_title'         => 'Home',
            'canonical_override' => 'https://evil.example/',
        ], JSON_THROW_ON_ERROR));
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $controller->post('page:home', 'en', $invalid)->getStatusCode());

        $valid = Request::create('/', 'POST', [], [], [], ['HTTP_X_CSRF_TOKEN' => 'tok', 'CONTENT_TYPE' => 'application/json'], json_encode([
            'metaTitle'         => 'Home',
            'metaDescription'   => 'Desc',
            'metaRobots'        => null,
            'canonicalOverride' => '/home',
            'openGraphImage'    => 'https://cdn.example/og.png',
        ], JSON_THROW_ON_ERROR));
        $savedContent = $controller->post('page:home', 'en', $valid)->getContent();
        self::assertNotFalse($savedContent);
        $saved = json_decode($savedContent, true);
        self::assertTrue($saved['ok']);
        self::assertFalse($saved['cleared']);

        $persisted = new SeoSurface();
        $persisted->setSurfaceKey('page:home')->setLocale('en')->setMetaTitle('X');
        $id = (new ReflectionClass($persisted))->getProperty('id');
        $id->setValue($persisted, 3);

        $repoClear = $this->createMock(SeoSurfaceRepository::class);
        $repoClear->method('findOneByKeyAndLocale')->willReturn($persisted);
        $repoClear->expects(self::once())->method('remove')->with($persisted);

        $clearController = $this->apiController($repoClear);
        $clearReq        = Request::create('/', 'POST', [], [], [], ['HTTP_X_CSRF_TOKEN' => 'tok', 'CONTENT_TYPE' => 'application/json'], json_encode([
            'metaTitle'         => '',
            'metaDescription'   => '',
            'metaRobots'        => null,
            'canonicalOverride' => '',
            'openGraphImage'    => '',
        ], JSON_THROW_ON_ERROR));
        $clearedContent = $clearController->post('page:home', 'en', $clearReq)->getContent();
        self::assertNotFalse($clearedContent);
        $cleared = json_decode($clearedContent, true);
        self::assertTrue($cleared['cleared']);
    }

    private ?RequestStack $requestStack = null;

    /**
     * API controller whose CSRF manager accepts the token value "tok" only.
     */
    private function apiController(SeoSurfaceRepository $repo, bool $formCsrf = false): SeoSurfaceApiController
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->method('getToken')->willReturnCallback(static fn (string $id): CsrfToken => new CsrfToken($id, 'tok'));
        $manager->method('isTokenValid')->willReturnCallback(
            static fn (CsrfToken $token): bool => $token->getId() === SeoSurfaceApiController::CSRF_TOKEN_ID && $token->getValue() === 'tok',
        );

        $controller = new SeoSurfaceApiController($repo, 'ROLE_ADMIN', $manager);
        $controller->setContainer($this->container(csrf: $formCsrf));

        return $controller;
    }

    private function requestStack(): RequestStack
    {
        return $this->requestStack ??= new RequestStack();
    }

    private function container(bool $csrf = false): ContainerInterface
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $name): string => '/' . $name);

        $twig = new Environment(new ArrayLoader([
            '@NowoSeoKitBundle/admin/settings.html.twig'       => 'settings-ok',
            '@NowoSeoKitBundle/admin/surfaces_index.html.twig' => 'surfaces-index',
            '@NowoSeoKitBundle/admin/surfaces_form.html.twig'  => 'surfaces-form',
        ]));

        $services = [
            'security.authorization_checker' => $auth,
            'form.factory'                   => $this->formFactory($csrf),
            'twig'                           => $twig,
            'router'                         => $router,
            'request_stack'                  => $this->requestStack(),
        ];

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => isset($services[$id]));
        $container->method('get')->willReturnCallback(static fn (string $id): object => $services[$id]);

        return $container;
    }

    private function formFactory(bool $csrf = false): FormFactoryInterface
    {
        $validator = Validation::createValidator();
        $builder   = Forms::createFormFactoryBuilder();
        if ($csrf) {
            $builder->addExtension(new CsrfExtension(new CsrfTokenManager()));
        }

        return $builder
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new ValidatorExtension($validator))
            ->addType(FormKitTestSupport::siteSettingsType())
            ->addType(FormKitTestSupport::surfaceType())
            ->getFormFactory();
    }

    private function siteConfig(): SeoSiteConfig
    {
        return new SeoSiteConfig(
            siteName: 'Nowo',
            titleTemplate: '%page%',
            indexable: true,
            robots: 'index, follow',
            openGraphImage: null,
            twitterSite: null,
            organisationLegalName: null,
            organisationLogo: null,
            contactEmail: null,
            contactPhone: null,
            streetAddress: null,
            postalCode: null,
            addressLocality: null,
            addressRegion: null,
            addressCountry: null,
            socialProfiles: [],
            googleSiteVerification: null,
            bingSiteVerification: null,
            byLocale: [],
        );
    }
}
