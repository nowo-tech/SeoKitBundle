<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Twig;

use Nowo\SeoKitBundle\Model\SeoMetadata;
use Nowo\SeoKitBundle\Service\SeoMetadataResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function is_string;

/**
 * Twig helpers: {{ nowo_seo_head() }} and {{ nowo_seo_metadata() }}.
 *
 * The JSON-LD script tag carries a CSP nonce when one is passed explicitly
 * (`{{ nowo_seo_head(csp_nonce) }}`) or, otherwise, when the current request has a
 * string `csp_nonce` attribute (set by the host's CSP listener).
 */
final class SeoKitExtension extends AbstractExtension
{
    /**
     * @param array{head: string, head_includes_title?: bool} $templates
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly SeoMetadataResolver $resolver,
        private readonly Environment $twig,
        private readonly array $templates,
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_seo_enabled', fn (): bool => $this->enabled),
            new TwigFunction('nowo_seo_metadata', $this->metadata(...)),
            new TwigFunction('nowo_seo_head', $this->renderHead(...), ['is_safe' => ['html']]),
        ];
    }

    public function metadata(): SeoMetadata
    {
        return $this->resolver->resolve();
    }

    public function renderHead(?string $cspNonce = null): string
    {
        if (!$this->enabled) {
            return '';
        }

        if ($cspNonce === null || $cspNonce === '') {
            $cspNonce = $this->requestCspNonce();
        }

        return $this->twig->render($this->templates['head'], [
            'seo'               => $this->resolver->resolve(),
            'seo_include_title' => (bool) ($this->templates['head_includes_title'] ?? true),
            'csp_nonce'         => $cspNonce,
        ]);
    }

    private function requestCspNonce(): ?string
    {
        $nonce = $this->requestStack?->getCurrentRequest()?->attributes->get('csp_nonce');

        return is_string($nonce) && $nonce !== '' ? $nonce : null;
    }
}
