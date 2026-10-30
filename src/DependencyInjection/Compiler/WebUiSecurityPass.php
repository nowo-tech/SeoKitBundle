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
 * exists (SecurityBundle with a firewall). `security.allow_unauthenticated: true` (public admin) is refused unless
 * `kernel.debug` is true and the environment is not `prod`.
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
            $debug       = $container->hasParameter('kernel.debug') && $container->getParameter('kernel.debug') === true;
            $environment = $container->hasParameter('kernel.environment') ? $container->getParameter('kernel.environment') : null;
            if (!$debug || $environment === 'prod') {
                throw new LogicException('NowoSeoKitBundle: security.allow_unauthenticated: true makes the admin UI and the JSON API public and is only allowed with kernel.debug (dev / demo), never in the prod environment. Set it to false and protect the admin with security.access_roles.');
            }

            return;
        }

        if (!$container->has('security.authorization_checker')) {
            throw new LogicException('NowoSeoKitBundle: the admin UI (web_ui.enabled) requires symfony/security-bundle with a firewall in front of its paths. Install and configure SecurityBundle, or set security.allow_unauthenticated: true (demo only).');
        }
    }
}
