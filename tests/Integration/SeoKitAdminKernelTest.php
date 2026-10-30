<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\SeoKitBundle\Security\ConfigurableSeoKitAccessChecker;
use Nowo\SeoKitBundle\Security\SeoKitAccessCheckerInterface;
use Nowo\SeoKitBundle\Ui\SeoKitAreaNav;
use Nowo\UiKitBundle\Navigation\AreaNavRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

use function base64_encode;
use function sys_get_temp_dir;

/**
 * REQ-UI-002 / REQ-UI-003 functional test: the admin pages behind the access checker (anonymous → 401, user without
 * the role → 403, admin → 200) render on the real UiKit admin layout and scaffolds, under `web_ui.path_prefix`.
 */
final class SeoKitAdminKernelTest extends TestCase
{
    private SeoKitAdminTestKernel $kernel;

    protected function setUp(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir() . SeoKitAdminTestKernel::DIR);
        $this->kernel = new SeoKitAdminTestKernel('test', false);
        $this->kernel->boot();

        $em = $this->container()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        (new Filesystem())->remove(sys_get_temp_dir() . SeoKitAdminTestKernel::DIR);
    }

    public function testAccessCheckerIsTheRoleCheckerAndTheAreaNavIsRegistered(): void
    {
        self::assertInstanceOf(ConfigurableSeoKitAccessChecker::class, $this->container()->get(SeoKitAccessCheckerInterface::class));

        $registry = $this->container()->get(AreaNavRegistry::class);
        self::assertInstanceOf(AreaNavRegistry::class, $registry);
        // no user: the admin navigation is not published
        self::assertFalse($registry->has(SeoKitAreaNav::AREA));

        $tokens = $this->container()->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('editor', null, ['ROLE_USER']), 'main', ['ROLE_USER']));
        self::assertFalse($registry->has(SeoKitAreaNav::AREA));

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('admin', null, ['ROLE_ADMIN']), 'main', ['ROLE_ADMIN']));
        self::assertTrue($registry->has(SeoKitAreaNav::AREA));
    }

    public function testAnonymousUserIsChallenged(): void
    {
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->get('/admin/seo/settings')->getStatusCode());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->get('/_nowo/seo/surfaces/page%3Ahome/en')->getStatusCode());
    }

    public function testUserWithoutTheRoleIsForbidden(): void
    {
        self::assertSame(Response::HTTP_FORBIDDEN, $this->get('/admin/seo/settings', 'editor')->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $this->get('/admin/seo/surfaces', 'editor')->getStatusCode());
    }

    public function testAdminSeesThePagesOnTheUiKitScaffolds(): void
    {
        $settings = $this->get('/admin/seo/settings', 'admin');
        self::assertSame(200, $settings->getStatusCode(), (string) $settings->getContent());
        $html = (string) $settings->getContent();
        self::assertStringContainsString('data-nowo-ui-page="form"', $html);
        self::assertStringContainsString('data-testid="seo-kit-admin-settings"', $html);
        self::assertStringContainsString('data-testid="nowo-seo-kit-settings-wording"', $html);
        self::assertStringContainsString('name="nowo_seo_site[_token]"', $html);
        self::assertStringContainsString('href="/admin/seo/surfaces"', $html);
        self::assertStringContainsString('nowo-ui-input', $html);

        $list = $this->get('/admin/seo/surfaces?locale=es', 'admin');
        self::assertSame(200, $list->getStatusCode(), (string) $list->getContent());
        self::assertStringContainsString('data-nowo-ui-page="list"', (string) $list->getContent());
        self::assertStringContainsString('<option value="es" selected="selected">ES</option>', (string) $list->getContent());
        self::assertStringContainsString('href="/admin/seo/surfaces/es/edit/blog:post/42"', (string) $list->getContent());
        self::assertStringContainsString('Missing title', (string) $list->getContent());
        $edit = $this->get('/admin/seo/surfaces/es/edit/blog:post/42', 'admin');
        self::assertSame(200, $edit->getStatusCode());
        self::assertStringContainsString('blog:post/42', (string) $edit->getContent());

        // 1.x links encoded the key twice (the controller still decodes them)
        $legacy = $this->get('/admin/seo/surfaces/en/edit/page%253Ahome', 'admin');
        self::assertSame(200, $legacy->getStatusCode());
        self::assertStringContainsString('page:home', (string) $legacy->getContent());

        $form = $this->get('/admin/seo/surfaces/en/edit/page:home', 'admin');
        self::assertSame(200, $form->getStatusCode(), (string) $form->getContent());
        self::assertStringContainsString('name="nowo_seo_surface[metaTitle]"', (string) $form->getContent());
        self::assertStringContainsString('href="/admin/seo/surfaces?locale=en"', (string) $form->getContent());
    }

    private function get(string $uri, ?string $user = null): Response
    {
        $server = $user === null ? [] : ['HTTP_AUTHORIZATION' => 'Basic ' . base64_encode($user . ':' . $user)];

        return $this->kernel->handle(Request::create('https://example.test' . $uri, 'GET', [], [], [], $server));
    }

    private function container(): ContainerInterface
    {
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }
}
