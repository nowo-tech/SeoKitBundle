<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service\Audit;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Host-provided list of SEO surfaces to audit for one locale.
 *
 * @phpstan-type AuditSubject array{
 *     surface_key: string,
 *     locale: string,
 *     path: string,
 *     title: string,
 *     description: string,
 *     robots: string,
 *     published: bool,
 *     has_override: bool
 * }
 */
#[AutoconfigureTag('nowo_seo_kit.audit_subject_provider')]
interface SeoAuditSubjectProviderInterface
{
    /**
     * @return list<AuditSubject>
     */
    public function subjectsForLocale(string $locale): array;
}
