<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function in_array;
use function is_string;

/**
 * Everything the site says about itself, editable instead of deployed.
 *
 * This is level 3 of the resolution order in `contracts/seo-head.md`: what a page falls back to
 * when neither the editor override nor the page type supplied a value. It used to come from the
 * translation catalogues and from environment parameters, which meant that changing the default
 * description of the site was a release. It is a singleton (id 1), like `InstanceSettings` and
 * `SiteAppearance`.
 *
 * Wording lives in {@see SeoSiteSettingsTranslation}, one row per locale; what is here is either
 * language-independent (the organisation, the social profiles, the verification tokens) or a rule
 * about how wording is assembled (`titleTemplate`).
 *
 * An empty field is not a value: the providers fall back to the translation catalogue, so a fresh
 * install with nothing typed in still renders a complete head.
 */
#[ORM\Entity]
#[ORM\Table(name: 'nowo_seo_site_settings')]
class SeoSiteSettings
{
    public const SINGLETON_ID = 1;

    /**
     * Where the page title goes in the pattern. A template without it would give every page of the
     * site the same title, so the form refuses to save one.
     */
    public const TITLE_PLACEHOLDER = '%page%';

    public const SITE_PLACEHOLDER = '%site%';

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::SINGLETON_ID;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 120)]
    private string $siteName = '';

    /**
     * How a page title becomes a `<title>`: `%page% · %site%`. The home is the exception a reader
     * would notice — "Home · nowo" says nothing — so it renders `homeTitleOverride` when set.
     */
    #[ORM\Column(length: 120)]
    private string $titleTemplate = '%page% · %site%';

    /**
     * The master switch that keeps a staging copy out of the index. It feeds the head, `robots.txt`
     * and the sitemap from one place, so the three can never contradict each other.
     */
    #[ORM\Column]
    private bool $indexable = true;

    /**
     * Applied when `indexable` is true; ignored when it is not, because `noindex` wins.
     */
    #[ORM\Column(length: 64)]
    private string $defaultRobots = 'index, follow';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $defaultOpenGraphImage = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $twitterSite = null;

    /* --- Organisation, for the `Organization` node of the knowledge graph --- */

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $organisationLegalName = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $organisationLogo = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $contactEmail = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $streetAddress = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $addressLocality = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $addressRegion = null;

    /** ISO 3166-1 alpha-2, which is what `PostalAddress` expects. */
    #[ORM\Column(length: 2, nullable: true)]
    private ?string $addressCountry = null;

    /**
     * Profiles that are the same organisation elsewhere, rendered as `sameAs`. This is what ties a
     * LinkedIn page to the site in the knowledge graph.
     *
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $socialProfiles = [];

    /* --- Ownership verification --- */

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $googleSiteVerification = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $bingSiteVerification = null;

    /** @var Collection<int, SeoSiteSettingsTranslation> */
    #[ORM\OneToMany(targetEntity: SeoSiteSettingsTranslation::class, mappedBy: 'settings', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSiteName(): string
    {
        return $this->siteName;
    }

    public function setSiteName(string $siteName): self
    {
        $this->siteName = trim($siteName);

        return $this;
    }

    public function getTitleTemplate(): string
    {
        return $this->titleTemplate;
    }

    public function setTitleTemplate(string $titleTemplate): self
    {
        $this->titleTemplate = trim($titleTemplate);

        return $this;
    }

    public function isIndexable(): bool
    {
        return $this->indexable;
    }

    public function setIndexable(bool $indexable): self
    {
        $this->indexable = $indexable;

        return $this;
    }

    public function getDefaultRobots(): string
    {
        return $this->defaultRobots;
    }

    public function setDefaultRobots(string $defaultRobots): self
    {
        $this->defaultRobots = trim($defaultRobots);

        return $this;
    }

    /**
     * What every page renders unless it says otherwise: the switch first, the directive second.
     */
    public function effectiveRobots(): string
    {
        if (!$this->indexable) {
            return 'noindex, nofollow';
        }

        return $this->defaultRobots === '' ? 'index, follow' : $this->defaultRobots;
    }

    public function getDefaultOpenGraphImage(): ?string
    {
        return $this->defaultOpenGraphImage;
    }

    public function setDefaultOpenGraphImage(?string $defaultOpenGraphImage): self
    {
        $this->defaultOpenGraphImage = $this->nullIfBlank($defaultOpenGraphImage);

        return $this;
    }

    public function getTwitterSite(): ?string
    {
        return $this->twitterSite;
    }

    public function setTwitterSite(?string $twitterSite): self
    {
        $handle = $this->nullIfBlank($twitterSite);
        if ($handle !== null && !str_starts_with($handle, '@')) {
            $handle = '@' . $handle;
        }

        $this->twitterSite = $handle;

        return $this;
    }

    public function getOrganisationLegalName(): ?string
    {
        return $this->organisationLegalName;
    }

    public function setOrganisationLegalName(?string $organisationLegalName): self
    {
        $this->organisationLegalName = $this->nullIfBlank($organisationLegalName);

        return $this;
    }

    public function getOrganisationLogo(): ?string
    {
        return $this->organisationLogo;
    }

    public function setOrganisationLogo(?string $organisationLogo): self
    {
        $this->organisationLogo = $this->nullIfBlank($organisationLogo);

        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): self
    {
        $this->contactEmail = $this->nullIfBlank($contactEmail);

        return $this;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): self
    {
        $this->contactPhone = $this->nullIfBlank($contactPhone);

        return $this;
    }

    public function getStreetAddress(): ?string
    {
        return $this->streetAddress;
    }

    public function setStreetAddress(?string $streetAddress): self
    {
        $this->streetAddress = $this->nullIfBlank($streetAddress);

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): self
    {
        $this->postalCode = $this->nullIfBlank($postalCode);

        return $this;
    }

    public function getAddressLocality(): ?string
    {
        return $this->addressLocality;
    }

    public function setAddressLocality(?string $addressLocality): self
    {
        $this->addressLocality = $this->nullIfBlank($addressLocality);

        return $this;
    }

    public function getAddressRegion(): ?string
    {
        return $this->addressRegion;
    }

    public function setAddressRegion(?string $addressRegion): self
    {
        $this->addressRegion = $this->nullIfBlank($addressRegion);

        return $this;
    }

    public function getAddressCountry(): ?string
    {
        return $this->addressCountry;
    }

    public function setAddressCountry(?string $addressCountry): self
    {
        $country              = $this->nullIfBlank($addressCountry);
        $this->addressCountry = $country === null ? null : strtoupper($country);

        return $this;
    }

    public function hasPostalAddress(): bool
    {
        return $this->streetAddress !== null || $this->addressLocality !== null || $this->postalCode !== null;
    }

    /**
     * @return list<string>
     */
    public function getSocialProfiles(): array
    {
        return $this->socialProfiles;
    }

    /**
     * @param array<int, string|null>|list<string> $socialProfiles
     */
    public function setSocialProfiles(array $socialProfiles): self
    {
        $clean = [];
        foreach ($socialProfiles as $profile) {
            $url = $this->nullIfBlank(is_string($profile) ? $profile : null);
            if ($url !== null && !in_array($url, $clean, true)) {
                $clean[] = $url;
            }
        }

        $this->socialProfiles = $clean;

        return $this;
    }

    public function getGoogleSiteVerification(): ?string
    {
        return $this->googleSiteVerification;
    }

    public function setGoogleSiteVerification(?string $googleSiteVerification): self
    {
        $this->googleSiteVerification = $this->nullIfBlank($googleSiteVerification);

        return $this;
    }

    public function getBingSiteVerification(): ?string
    {
        return $this->bingSiteVerification;
    }

    public function setBingSiteVerification(?string $bingSiteVerification): self
    {
        $this->bingSiteVerification = $this->nullIfBlank($bingSiteVerification);

        return $this;
    }

    /**
     * @return Collection<int, SeoSiteSettingsTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function findTranslation(string $locale): ?SeoSiteSettingsTranslation
    {
        $locale = strtolower(trim($locale));
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    public function translationFor(string $locale): SeoSiteSettingsTranslation
    {
        $translation = $this->findTranslation($locale);
        if ($translation instanceof SeoSiteSettingsTranslation) {
            return $translation;
        }

        $translation = new SeoSiteSettingsTranslation();
        $translation->setLocale($locale);
        $this->addTranslation($translation);

        return $translation;
    }

    public function addTranslation(SeoSiteSettingsTranslation $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setSettings($this);
        }

        return $this;
    }

    public function removeTranslation(SeoSiteSettingsTranslation $translation): self
    {
        $this->translations->removeElement($translation);

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touchUpdatedAt(): self
    {
        $this->updatedAt = new DateTimeImmutable();

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
