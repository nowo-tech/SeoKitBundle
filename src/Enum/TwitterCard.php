<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Enum;

use function array_column;

/**
 * `twitter:card` types accepted by `defaults.twitter.card`.
 */
enum TwitterCard: string
{
    case Summary           = 'summary';
    case SummaryLargeImage = 'summary_large_image';
    case App               = 'app';
    case Player            = 'player';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
