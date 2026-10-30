<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\DependencyInjection\Compiler;

use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_array;

/**
 * Fails closed when the admin UI would run without form CSRF protection.
 *
 * The settings and surface forms (`SeoSurfaceType`, site settings) rely on Symfony form CSRF. A host that disables
 * `framework.form.csrf_protection` (or has no CSRF token manager) would accept cross-site posts, so the container
 * refuses to compile instead while `web_ui.enabled` (1.x: `admin.enabled`) is true.
 */
final class CsrfProtectionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('nowo_seo_kit.web_ui')) {
            return;
        }

        $webUi = $container->getParameter('nowo_seo_kit.web_ui');
        if (!is_array($webUi) || ($webUi['enabled'] ?? false) !== true) {
            return;
        }

        $formCsrfDisabled = $container->hasParameter('form.type_extension.csrf.enabled')
            && $container->getParameter('form.type_extension.csrf.enabled') === false;

        if (
            !$container->has('security.csrf.token_manager')
            || !$container->hasDefinition('form.type_extension.csrf')
            || $formCsrfDisabled
        ) {
            throw new LogicException('NowoSeoKitBundle: the admin UI (web_ui.enabled) requires CSRF protection. Enable framework.csrf_protection and framework.form.csrf_protection (composer require symfony/security-csrf), or set web_ui.enabled: false.');
        }
    }
}
