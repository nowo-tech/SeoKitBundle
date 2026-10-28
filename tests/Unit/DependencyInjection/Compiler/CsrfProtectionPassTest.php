<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\DependencyInjection\Compiler;

use LogicException;
use Nowo\SeoKitBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class CsrfProtectionPassTest extends TestCase
{
    public function testSkipsWhenBundleNotLoadedOrAdminDisabled(): void
    {
        $container = new ContainerBuilder();
        (new CsrfProtectionPass())->process($container);

        $container->setParameter('nowo_seo_kit.admin', ['enabled' => false]);
        (new CsrfProtectionPass())->process($container);

        $container->setParameter('nowo_seo_kit.admin', 'not-an-array');
        (new CsrfProtectionPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testPassesWithFormCsrf(): void
    {
        $container = $this->adminContainer();
        $container->setDefinition('security.csrf.token_manager', new Definition(stdClass::class));
        $container->setDefinition('form.type_extension.csrf', new Definition(stdClass::class));
        $container->setParameter('form.type_extension.csrf.enabled', true);

        (new CsrfProtectionPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testFailsWithoutTokenManager(): void
    {
        $container = $this->adminContainer();
        $container->setDefinition('form.type_extension.csrf', new Definition(stdClass::class));

        $this->expectException(LogicException::class);
        (new CsrfProtectionPass())->process($container);
    }

    public function testFailsWhenFormCsrfIsDisabled(): void
    {
        $container = $this->adminContainer();
        $container->setDefinition('security.csrf.token_manager', new Definition(stdClass::class));
        $container->setDefinition('form.type_extension.csrf', new Definition(stdClass::class));
        $container->setParameter('form.type_extension.csrf.enabled', false);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('admin.enabled');
        (new CsrfProtectionPass())->process($container);
    }

    private function adminContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_seo_kit.admin', ['enabled' => true]);

        return $container;
    }
}
