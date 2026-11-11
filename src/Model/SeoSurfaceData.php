<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model;

use InvalidArgumentException;
use Nowo\SeoKitBundle\Service\SeoSurfaceStoreInterface;

use function strtolower;
use function trim;

/**
 * Editor override of one SEO surface in one locale, as plain data (since 1.24.0). Read and written through
 * {@see SeoSurfaceStoreInterface}; the Doctrine entity stays inside the bundle.
 *
 * Values are normalised like the entity: strings are trimmed and an empty string means null (inherit), the locale is
 * lower-cased, and an empty structured-data array means null.
 */
final readonly class SeoSurfaceData
{
    public string $surfaceKey;

    public string $locale;

    public ?string $metaTitle;

    public ?string $metaDescription;

    public ?string $metaRobots;

    public ?string $canonicalOverride;

    public ?string $openGraphImage;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $structuredDataExtra;

    /**
     * @param array<string, mixed>|null $structuredDataExtra
     */
    public function __construct(
        string $surfaceKey,
        string $locale,
        ?string $metaTitle = null,
        ?string $metaDescription = null,
        ?string $metaRobots = null,
        ?string $canonicalOverride = null,
        ?string $openGraphImage = null,
        ?array $structuredDataExtra = null,
    ) {
        $surfaceKey = trim($surfaceKey);
        $locale     = strtolower(trim($locale));
        if ($surfaceKey === '' || $locale === '') {
            throw new InvalidArgumentException('SeoSurfaceData needs a non-empty surface key and locale.');
        }

        $this->surfaceKey          = $surfaceKey;
        $this->locale              = $locale;
        $this->metaTitle           = $this->clean($metaTitle);
        $this->metaDescription     = $this->clean($metaDescription);
        $this->metaRobots          = $this->clean($metaRobots);
        $this->canonicalOverride   = $this->clean($canonicalOverride);
        $this->openGraphImage      = $this->clean($openGraphImage);
        $this->structuredDataExtra = $structuredDataExtra === [] ? null : $structuredDataExtra;
    }

    /**
     * Whether every override field is empty (the surface inherits everything).
     */
    public function isEmpty(): bool
    {
        return $this->metaTitle === null
            && $this->metaDescription === null
            && $this->metaRobots === null
            && $this->canonicalOverride === null
            && $this->openGraphImage === null
            && $this->structuredDataExtra === null;
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
