<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model\StructuredData;

use Override;

/**
 * FAQPage JSON-LD from catalogue Q&amp;A (do not invent answers).
 */
final class FaqPageNode extends AbstractNode
{
    /**
     * @param list<array{name: string, acceptedAnswer: string}> $questions
     */
    public function __construct(
        private readonly string $url,
        private readonly array $questions,
    ) {
    }

    #[Override]
    public function toArray(): array
    {
        $entities = [];
        foreach ($this->questions as $question) {
            $name   = trim($question['name']);
            $answer = trim($question['acceptedAnswer']);
            if ($name === '' || $answer === '') {
                continue;
            }
            $entities[] = [
                '@type'          => 'Question',
                'name'           => $name,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $answer,
                ],
            ];
        }

        return self::prune([
            '@type'      => 'FAQPage',
            'url'        => $this->url,
            'mainEntity' => $entities,
        ]);
    }
}
