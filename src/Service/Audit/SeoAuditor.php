<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service\Audit;

use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Builds audit rows from host subject providers + shared rules.
 */
final readonly class SeoAuditor
{
    /**
     * @param iterable<SeoAuditSubjectProviderInterface> $subjectProviders
     */
    public function __construct(
        private SeoAuditRules $rules,
        #[TaggedIterator('nowo_seo_kit.audit_subject_provider')]
        private iterable $subjectProviders = [],
        private ?SeoSiteConfigProviderInterface $configProvider = null,
        private bool $fallbackSiteIndexable = true,
    ) {
    }

    /**
     * @return list<SeoAuditRow>
     */
    public function forLocale(string $locale): array
    {
        $siteIsIndexable = $this->configProvider?->get()->indexable ?? $this->fallbackSiteIndexable;
        $rows            = [];

        foreach ($this->subjectProviders as $provider) {
            foreach ($provider->subjectsForLocale($locale) as $subject) {
                $problems = $this->rules->problemsOf(
                    $subject['title'],
                    $subject['description'],
                    $subject['robots'],
                    $subject['published'],
                    $siteIsIndexable,
                );

                $rows[] = new SeoAuditRow(
                    surfaceKey: $subject['surface_key'],
                    locale: $subject['locale'],
                    path: $subject['path'],
                    title: $subject['title'],
                    description: $subject['description'],
                    robots: $subject['robots'],
                    published: $subject['published'],
                    hasOverride: $subject['has_override'],
                    problems: $problems,
                );
            }
        }

        return $this->rules->annotateDuplicates($rows);
    }
}
