<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Model\StructuredData;

use Override;

/**
 * Blog post JSON-LD as BlogPosting (author + date + optional publisher).
 */
final class BlogPostingNode extends AbstractNode
{
    public function __construct(
        private readonly string $headline,
        private readonly string $url,
        private readonly string $inLanguage,
        private readonly ?string $description = null,
        private readonly ?string $datePublished = null,
        private readonly ?string $image = null,
        private readonly ?string $authorName = null,
        private readonly ?string $authorId = null,
        private readonly ?string $publisherId = null,
    ) {
    }

    #[Override]
    public function toArray(): array
    {
        $author = null;
        if ($this->authorName !== null && trim($this->authorName) !== '') {
            $author = self::prune([
                '@type' => 'Person',
                '@id'   => $this->authorId,
                'name'  => $this->authorName,
            ]);
        }

        $publisher = null;
        if ($this->publisherId !== null && $this->publisherId !== '') {
            $publisher = ['@id' => $this->publisherId];
        }

        return self::prune([
            '@type'            => 'BlogPosting',
            'headline'         => $this->headline,
            'url'              => $this->url,
            'mainEntityOfPage' => $this->url,
            'description'      => $this->description,
            'inLanguage'       => $this->inLanguage,
            'datePublished'    => $this->datePublished,
            'image'            => $this->image,
            'author'           => $author,
            'publisher'        => $publisher,
        ]);
    }
}
