<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Model\HreflangSet;
use Nowo\SeoKitBundle\Model\PageHead;
use Nowo\SeoKitBundle\Model\PageHeadDefaults;
use Nowo\SeoKitBundle\Model\PageHeadInput;
use Nowo\SeoKitBundle\Model\StructuredData\BreadcrumbListNode;
use Nowo\SeoKitBundle\Model\StructuredData\SoftwareApplicationNode;
use Nowo\SeoKitBundle\Model\StructuredData\StructuredDataGraph;
use Nowo\SeoKitBundle\Model\StructuredData\StructuredDataNode;
use Nowo\SeoKitBundle\Model\StructuredData\WebPageNode;

final readonly class PageHeadResolver
{
    /** @param array<string, string> $openGraphRegions */
    public function __construct(
        private AbsoluteUrlBuilder $urls,
        private HreflangSetBuilder $hreflangSetBuilder,
        private PageHeadDefaultsProviderInterface $defaultsProvider,
        private ?PageHeadSiteGraphProviderInterface $siteGraphProvider = null,
        private ?PageHeadTitleComposerInterface $titleComposer = null,
        private array $openGraphRegions = [],
    ) {
    }

    public function resolve(PageHeadInput $input): PageHead
    {
        $defaults = $this->defaultsProvider->forLocale($input->locale);
        $title = null !== $this->titleComposer
            ? $this->titleComposer->compose($input, $defaults)
            : $this->defaultTitle($input, $defaults);
        $description = $this->firstNonEmpty($input->descriptionOverride, $input->templateDescription, $defaults->description);
        $robots = $this->firstNonEmpty($input->robotsOverride, $defaults->robots);
        $canonical = $this->clean($input->canonicalOverride) ?? $this->urls->fromPath($input->path);
        $hreflang = $this->hreflangSetBuilder->build($input->pathsByLocale);
        $ogImage = $this->clean($input->ogImage) ?? $defaults->ogImage;

        return new PageHead(
            title: $title,
            description: $description,
            canonical: $canonical,
            robots: $robots,
            hreflang: $hreflang,
            ogSiteName: $defaults->siteName,
            ogType: $this->firstNonEmpty($input->ogType, 'website'),
            ogTitle: $this->firstNonEmpty($input->ogTitle, $title),
            ogDescription: $this->firstNonEmpty($input->ogDescription, $description),
            ogImage: $ogImage,
            ogImageWidth: null === $ogImage ? null : $defaults->ogImageWidth,
            ogImageHeight: null === $ogImage ? null : $defaults->ogImageHeight,
            ogLocale: $this->openGraphLocale($input->locale),
            ogLocaleAlternates: $this->openGraphAlternates($input->locale, $hreflang),
            twitterCard: null === $ogImage ? 'summary' : 'summary_large_image',
            structuredData: $this->structuredData($input, $title, $description, $canonical),
            pathsByLocale: $input->pathsByLocale,
            breadcrumbs: $input->breadcrumbs,
        );
    }

    private function defaultTitle(PageHeadInput $input, PageHeadDefaults $defaults): string
    {
        $typed = $this->clean($input->titleOverride);
        if (null !== $typed) {
            return $typed;
        }

        return $this->firstNonEmpty($input->templateTitle, $defaults->title);
    }

    private function structuredData(PageHeadInput $input, string $title, string $description, string $canonical): StructuredDataGraph
    {
        $graph = $this->siteGraphProvider?->forLocale($input->locale) ?? new StructuredDataGraph();
        $nodes = [...$input->structuredData, ...$this->pageNodes($input, $title, $description, $canonical)];

        return [] === $nodes ? $graph : $graph->with(...$nodes);
    }

    /** @return list<StructuredDataNode> */
    private function pageNodes(PageHeadInput $input, string $title, string $description, string $canonical): array
    {
        $nodes = [];
        if ([] !== $input->breadcrumbs) {
            $items = [];
            foreach ($input->breadcrumbs as $crumb) {
                $items[] = ['name' => $crumb['name'], 'url' => $this->urls->fromPath($crumb['path'])];
            }
            $nodes[] = new BreadcrumbListNode($items);
        }
        $name = $this->firstNonEmpty($input->templateTitle, $title);
        $page = match ($input->schemaType) {
            'SoftwareApplication' => new SoftwareApplicationNode($name, $canonical, $description, $input->locale),
            'WebPage' => new WebPageNode($name, $canonical, $description, $input->locale),
            default => null,
        };
        if ($page instanceof StructuredDataNode) {
            $nodes[] = $page;
        }

        return $nodes;
    }

    private function openGraphLocale(string $locale): string
    {
        return $locale.'_'.($this->openGraphRegions[$locale] ?? strtoupper($locale));
    }

    /** @return list<string> */
    private function openGraphAlternates(string $locale, HreflangSet $hreflang): array
    {
        $alternates = [];
        foreach (array_keys($hreflang->alternates()) as $published) {
            if ($published !== $locale) {
                $alternates[] = $this->openGraphLocale($published);
            }
        }

        return $alternates;
    }

    private function firstNonEmpty(?string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            $clean = $this->clean($candidate);
            if (null !== $clean) {
                return $clean;
            }
        }

        return '';
    }

    private function clean(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
