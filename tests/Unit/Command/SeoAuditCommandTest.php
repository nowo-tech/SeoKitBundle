<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Command;

use Nowo\SeoKitBundle\Command\SeoAuditCommand;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditor;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditRules;
use Nowo\SeoKitBundle\Service\Audit\SeoAuditSubjectProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SeoAuditCommandTest extends TestCase
{
    public function testSuccessWhenNoProblems(): void
    {
        $tester = new CommandTester(new SeoAuditCommand($this->auditor([
            [
                'surface_key'  => 'home',
                'locale'       => 'en',
                'path'         => '/',
                'title'        => 'Home title ok',
                'description'  => str_repeat('d', 80),
                'robots'       => 'index, follow',
                'published'    => true,
                'has_override' => false,
            ],
        ]), ['en']));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('no problems found', $tester->getDisplay());
    }

    public function testFailsOnProblemsUnlessLenient(): void
    {
        $auditor = $this->auditor([
            [
                'surface_key'  => 'home',
                'locale'       => 'en',
                'path'         => '/',
                'title'        => '',
                'description'  => '',
                'robots'       => 'index, follow',
                'published'    => true,
                'has_override' => false,
            ],
        ]);

        $failing = new CommandTester(new SeoAuditCommand($auditor, ['en']));
        self::assertSame(Command::FAILURE, $failing->execute(['--locale' => 'en']));
        self::assertStringContainsString('have something to fix', $failing->getDisplay());

        $lenient = new CommandTester(new SeoAuditCommand($auditor, ['en']));
        self::assertSame(Command::SUCCESS, $lenient->execute(['--lenient' => true]));
        self::assertStringContainsString('have something to fix', $lenient->getDisplay());
    }

    /**
     * @param list<array{
     *     surface_key: string,
     *     locale: string,
     *     path: string,
     *     title: string,
     *     description: string,
     *     robots: string,
     *     published: bool,
     *     has_override: bool
     * }> $subjects
     */
    private function auditor(array $subjects): SeoAuditor
    {
        $provider = new class($subjects) implements SeoAuditSubjectProviderInterface {
            /**
             * @param list<array{
             *     surface_key: string,
             *     locale: string,
             *     path: string,
             *     title: string,
             *     description: string,
             *     robots: string,
             *     published: bool,
             *     has_override: bool
             * }> $subjects
             */
            public function __construct(private readonly array $subjects)
            {
            }

            public function subjectsForLocale(string $locale): array
            {
                return $this->subjects;
            }
        };

        return new SeoAuditor(new SeoAuditRules(), [$provider]);
    }
}
