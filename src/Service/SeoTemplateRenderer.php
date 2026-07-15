<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

/**
 * Replaces `{placeholder}` tokens in SEO title/description templates.
 */
final class SeoTemplateRenderer
{
    /**
     * @param array<string, scalar|null> $variables
     */
    public function render(?string $template, array $variables): ?string
    {
        if ($template === null || $template === '') {
            return $template;
        }

        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{'.$key.'}'] = (string) ($value ?? '');
        }

        return strtr($template, $replacements);
    }
}
