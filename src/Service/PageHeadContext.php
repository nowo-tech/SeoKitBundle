<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use LogicException;
use Nowo\SeoKitBundle\Model\PageHead;
use Nowo\SeoKitBundle\Model\PageHeadInput;
use Override;
use Symfony\Contracts\Service\ResetInterface;

final class PageHeadContext implements ResetInterface
{
    private ?PageHeadInput $input = null;
    private ?PageHead $resolved = null;

    public function __construct(private readonly PageHeadResolver $resolver)
    {
    }

    public function describe(PageHeadInput $input): void
    {
        $this->input = $input;
        $this->resolved = null;
    }

    public function isDescribed(): bool
    {
        return $this->input instanceof PageHeadInput;
    }

    public function pageHead(): PageHead
    {
        if (!$this->input instanceof PageHeadInput) {
            throw new LogicException(sprintf('No page metadata was described. Call %s::describe() first.', self::class));
        }

        return $this->resolved ??= $this->resolver->resolve($this->input);
    }

    #[Override]
    public function reset(): void
    {
        $this->input = null;
        $this->resolved = null;
    }
}
