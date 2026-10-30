<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\DependencyInjection\Compiler;

use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_array;

/**
 * Fails closed when the admin UI would run without Symfony Security (REQ-UI-002): with `web_ui.enabled` and
 * `security.allow_unauthenticated: false` the container refuses to compile unless `security.authorization_checker`
 * exists (SecurityBundle with a firewall).
 */
final class WebUiSecurityPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('nowo_seo_kit.web_ui') || !$container->hasParameter('nowo_seo_kit.security')) {
            return;
        }

        $webUi    = $container->getParameter('nowo_seo_kit.web_ui');
        $security = $container->getParameter('nowo_seo_kit.security');
        if (!is_array($webUi) || ($webUi['enabled'] ?? false) !== true) {
            return;
        }
        if (is_array($security) && ($security['allow_unauthenticated'] ?? false) === true) {
            return;
        }

        if (!$container->has('security.authorization_checker')) {
            throw new LogicException('NowoSeoKitBundle: the admin UI (web_ui.enabled) requires symfony/security-bundle with a firewall in front of its paths. Install and configure SecurityBundle, or set security.allow_unauthenticated: true (demo only).');
        }
    }
}
