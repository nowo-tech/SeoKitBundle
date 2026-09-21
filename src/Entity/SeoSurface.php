<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Editor override for one SEO surface (page, blog post, legal doc, …) in one locale.
 *
 * Surfaces are identified by a host-chosen string key (e.g. `page:home`, `blog:42`), not a foreign
 * key to a CMS entity — the kit stays free of host Domain models.
 *
 * Every field is nullable: null means inherit from page-type defaults, then site settings.
 */
#[ORM\Entity]
#[ORM\Table(name: 'nowo_seo_surface')]
#[ORM\UniqueConstraint(name: 'uniq_nowo_seo_surface_key_locale', columns: ['surface_key', 'locale'])]
class SeoSurface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (Doctrine GeneratedValue)

    #[ORM\Column(name: 'surface_key', length: 180)]
    private string $surfaceKey = '';

    #[ORM\Column(length: 8)]
    private string $locale = 'en';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $metaRobots = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $canonicalOverride = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $openGraphImage = null;

    /**
     * Extra structured-data payload stored as JSON; hosts should re-serialise through typed nodes
     * before embedding in a script tag.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $structuredDataExtra = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSurfaceKey(): string
    {
        return $this->surfaceKey;
    }

    public function setSurfaceKey(string $surfaceKey): self
    {
        $this->surfaceKey = trim($surfaceKey);

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = strtolower(trim($locale));

        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): self
    {
        $this->metaTitle = $this->clean($metaTitle);

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): self
    {
        $this->metaDescription = $this->clean($metaDescription);

        return $this;
    }

    public function getMetaRobots(): ?string
    {
        return $this->metaRobots;
    }

    public function setMetaRobots(?string $metaRobots): self
    {
        $this->metaRobots = $this->clean($metaRobots);

        return $this;
    }

    public function getCanonicalOverride(): ?string
    {
        return $this->canonicalOverride;
    }

    public function setCanonicalOverride(?string $canonicalOverride): self
    {
        $this->canonicalOverride = $this->clean($canonicalOverride);

        return $this;
    }

    public function getOpenGraphImage(): ?string
    {
        return $this->openGraphImage;
    }

    public function setOpenGraphImage(?string $openGraphImage): self
    {
        $this->openGraphImage = $this->clean($openGraphImage);

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStructuredDataExtra(): ?array
    {
        return $this->structuredDataExtra;
    }

    /**
     * @param array<string, mixed>|null $structuredDataExtra
     */
    public function setStructuredDataExtra(?array $structuredDataExtra): self
    {
        $this->structuredDataExtra = $structuredDataExtra === [] ? null : $structuredDataExtra;

        return $this;
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
