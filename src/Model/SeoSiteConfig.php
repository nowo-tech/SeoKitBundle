<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model;

/**
 * Immutable snapshot of editable site SEO settings, safe to cache.
 *
 * The Doctrine entity is not cached: it carries persistence state and per-locale collections.
 * This DTO flattens the same values so a page render costs no query once the snapshot is warm.
 */
final readonly class SeoSiteConfig
{
    /**
     * @param array<string, array{title: ?string, description: ?string, homeTitle: ?string, organisationDescription: ?string}> $byLocale
     * @param list<string> $socialProfiles
     */
    public function __construct(
        public string $siteName,
        public string $titleTemplate,
        public bool $indexable,
        public string $robots,
        public ?string $openGraphImage,
        public ?string $twitterSite,
        public ?string $organisationLegalName,
        public ?string $organisationLogo,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public ?string $streetAddress,
        public ?string $postalCode,
        public ?string $addressLocality,
        public ?string $addressRegion,
        public ?string $addressCountry,
        public array $socialProfiles,
        public ?string $googleSiteVerification,
        public ?string $bingSiteVerification,
        public array $byLocale,
    ) {
    }

    public function titleFor(string $locale): ?string
    {
        return $this->byLocale[$locale]['title'] ?? null;
    }

    public function descriptionFor(string $locale): ?string
    {
        return $this->byLocale[$locale]['description'] ?? null;
    }

    public function homeTitleFor(string $locale): ?string
    {
        return $this->byLocale[$locale]['homeTitle'] ?? null;
    }

    public function organisationDescriptionFor(string $locale): ?string
    {
        return $this->byLocale[$locale]['organisationDescription'] ?? null;
    }

    /**
     * Applies the editor title template. A template that lost its `%page%` placeholder is ignored
     * so every page does not collapse to the same title.
     */
    public function composeTitle(string $pageTitle): string
    {
        $template = $this->titleTemplate;
        if ($pageTitle === '') {
            return $this->siteName;
        }

        if (!str_contains($template, '%page%')) {
            return $pageTitle;
        }

        $composed = str_replace(['%page%', '%site%'], [rtrim($pageTitle, '.'), $this->siteName], $template);

        return trim($composed, " \t\n\r\0\x0B·-|—");
    }

    public function hasPostalAddress(): bool
    {
        return $this->streetAddress !== null || $this->addressLocality !== null || $this->postalCode !== null;
    }
}
