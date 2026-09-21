<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * What the site says about itself in one language.
 *
 * Every field is optional and an empty one inherits the translation catalogue, so a locale nobody
 * has written yet still renders a complete head instead of an empty title.
 */
#[ORM\Entity]
#[ORM\Table(name: 'nowo_seo_site_settings_translation')]
#[ORM\UniqueConstraint(name: 'uniq_nowo_seo_site_settings_translation_locale', columns: ['settings_id', 'locale'])]
class SeoSiteSettingsTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (Doctrine GeneratedValue)

    #[ORM\ManyToOne(targetEntity: SeoSiteSettings::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'settings_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?SeoSiteSettings $settings = null;

    #[ORM\Column(length: 8)]
    private string $locale = 'es';

    /**
     * The title of a page that supplied none, and the base of the home title.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $defaultTitle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $defaultDescription = null;

    /**
     * The home is the one page whose title is not "something · site": rendering the template there
     * gives "Home · nowo", which wastes the most valuable title of the site.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $homeTitle = null;

    /**
     * The `description` of the `Organization` node, which is not the description of the home page:
     * one describes the company, the other sells the page.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $organisationDescription = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSettings(): ?SeoSiteSettings
    {
        return $this->settings;
    }

    public function setSettings(?SeoSiteSettings $settings): self
    {
        $this->settings = $settings;

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

    public function getDefaultTitle(): ?string
    {
        return $this->defaultTitle;
    }

    public function setDefaultTitle(?string $defaultTitle): self
    {
        $this->defaultTitle = $this->nullIfBlank($defaultTitle);

        return $this;
    }

    public function getDefaultDescription(): ?string
    {
        return $this->defaultDescription;
    }

    public function setDefaultDescription(?string $defaultDescription): self
    {
        $this->defaultDescription = $this->nullIfBlank($defaultDescription);

        return $this;
    }

    public function getHomeTitle(): ?string
    {
        return $this->homeTitle;
    }

    public function setHomeTitle(?string $homeTitle): self
    {
        $this->homeTitle = $this->nullIfBlank($homeTitle);

        return $this;
    }

    public function getOrganisationDescription(): ?string
    {
        return $this->organisationDescription;
    }

    public function setOrganisationDescription(?string $organisationDescription): self
    {
        $this->organisationDescription = $this->nullIfBlank($organisationDescription);

        return $this;
    }

    private function nullIfBlank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
