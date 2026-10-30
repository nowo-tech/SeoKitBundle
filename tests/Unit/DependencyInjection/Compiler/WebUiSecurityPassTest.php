<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\DependencyInjection\Compiler;

use LogicException;
use Nowo\SeoKitBundle\DependencyInjection\Compiler\WebUiSecurityPass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class WebUiSecurityPassTest extends TestCase
{
    public function testSkipsWithoutParametersOrWhileTheWebUiIsDisabled(): void
    {
        $container = new ContainerBuilder();
        (new WebUiSecurityPass())->process($container);

        $container->setParameter('nowo_seo_kit.web_ui', ['enabled' => true]);
        (new WebUiSecurityPass())->process($container);

        $container->setParameter('nowo_seo_kit.security', ['allow_unauthenticated' => false]);
        $container->setParameter('nowo_seo_kit.web_ui', ['enabled' => false]);
        (new WebUiSecurityPass())->process($container);

        $container->setParameter('nowo_seo_kit.web_ui', 'not-an-array');
        (new WebUiSecurityPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testAllowUnauthenticatedNeedsNoSecurityBundle(): void
    {
        $container = $this->container(['allow_unauthenticated' => true]);
        (new WebUiSecurityPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testPassesWithTheAuthorizationChecker(): void
    {
        $container = $this->container(['allow_unauthenticated' => false]);
        $container->register('security.authorization_checker', stdClass::class);
        (new WebUiSecurityPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testFailsWithoutSecurityBundle(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires symfony/security-bundle');
        (new WebUiSecurityPass())->process($this->container(['allow_unauthenticated' => false]));
    }

    public function testFailsWhenTheSecurityParameterIsMalformed(): void
    {
        $this->expectException(LogicException::class);
        (new WebUiSecurityPass())->process($this->container('not-an-array'));
    }

    /**
     * @param array<string, bool>|string $security
     */
    private function container(array|string $security): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_seo_kit.web_ui', ['enabled' => true]);
        $container->setParameter('nowo_seo_kit.security', $security);

        return $container;
    }
}
