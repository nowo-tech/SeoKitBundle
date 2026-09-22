<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Model\PageHeadDefaults;
use Nowo\SeoKitBundle\Model\PageHeadInput;

interface PageHeadTitleComposerInterface
{
    public function compose(PageHeadInput $input, PageHeadDefaults $defaults): string;
}
