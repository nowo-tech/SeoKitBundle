<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Command;

use Nowo\SeoKitBundle\Service\Audit\SeoAuditor;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditRow;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function sprintf;

/**
 * Surface-by-surface SEO audit for CI: fail on invisible ranking defects.
 */
#[AsCommand(
    name: 'nowo:seo:audit',
    description: 'Report resolved head metadata for every audit subject and fail on ranking defects',
)]
final class SeoAuditCommand extends Command
{
    /**
     * @param list<string> $locales
     */
    public function __construct(
        private readonly SeoAuditor $auditor,
        private readonly array $locales = ['en'],
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Audit one locale instead of every configured one')]
        ?string $locale = null,
        #[Option(description: 'Report findings without failing the command')]
        bool $lenient = false,
    ): int {
        $locales  = $locale === null ? $this->locales : [$locale];
        $problems = 0;
        $surfaces = 0;

        foreach ($locales as $current) {
            $rows = $this->auditor->forLocale($current);
            $surfaces += count($rows);
            $failing = array_values(array_filter($rows, static fn (SeoAuditRow $row): bool => !$row->isClean()));
            $problems += count($failing);

            $io->section(sprintf('%s — %d surfaces, %d with something to fix', $current, count($rows), count($failing)));

            if ($failing === []) {
                $io->text('Nothing to report.');

                continue;
            }

            $io->table(
                ['Surface', 'Path', 'Problems'],
                array_map(static fn (SeoAuditRow $row): array => [
                    $row->surfaceKey,
                    $row->path,
                    implode(', ', $row->problems()),
                ], $failing),
            );
        }

        if ($problems === 0) {
            $io->success(sprintf('%d surfaces audited, no problems found.', $surfaces));

            return Command::SUCCESS;
        }

        $summary = sprintf('%d of %d audited surfaces have something to fix.', $problems, $surfaces);

        if ($lenient) {
            $io->warning($summary);

            return Command::SUCCESS;
        }

        $io->error($summary);

        return Command::FAILURE;
    }
}
