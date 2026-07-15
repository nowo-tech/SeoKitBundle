<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Twig;

use Nowo\SeoKitBundle\Model\SeoMetadata;
use Nowo\SeoKitBundle\Service\SeoMetadataResolver;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig helpers: {{ nowo_seo_head() }} and {{ nowo_seo_metadata() }}.
 */
final class SeoKitExtension extends AbstractExtension
{
    /**
     * @param array{head: string} $templates
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly SeoMetadataResolver $resolver,
        private readonly Environment $twig,
        private readonly array $templates,
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

    public function renderHead(): string
    {
        if (!$this->enabled) {
            return '';
        }

        $seo = $this->resolver->resolve();

        return $this->twig->render($this->templates['head'], ['seo' => $seo]);
    }
}
