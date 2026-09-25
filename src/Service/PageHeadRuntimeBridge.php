<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Service;

use Nowo\SeoKitBundle\Model\PageHead;

final readonly class PageHeadRuntimeBridge
{
    public function __construct(private SeoRuntime $seoRuntime)
    {
    }

    public function apply(PageHead $meta): void
    {
        $alternates = [];
        foreach ($meta->hreflang->alternates() as $locale => $url) {
            $alternates[] = ['locale' => $locale, 'url' => $url, 'hreflang' => $locale];
        }
        if ($meta->hreflang->xDefault() !== null) {
            $alternates[] = [
                'locale'   => 'x-default',
                'url'      => $meta->hreflang->xDefault(),
                'hreflang' => 'x-default',
            ];
        }

        $payload = [
            'title'       => $meta->title,
            'title_final' => true,
            'description' => $meta->description,
            'robots'      => $meta->robots,
            'canonical'   => $meta->canonical,
            'alternates'  => $alternates,
            'open_graph'  => [
                'enabled'           => true,
                'type'              => $meta->ogType,
                'title'             => $meta->ogTitle,
                'description'       => $meta->ogDescription,
                'image'             => $meta->ogImage,
                'image_width'       => $meta->ogImageWidth,
                'image_height'      => $meta->ogImageHeight,
                'image_alt'         => $meta->ogTitle,
                'site_name'         => $meta->ogSiteName,
                'locale'            => $meta->ogLocale,
                'locale_alternates' => $meta->ogLocaleAlternates,
            ],
            'twitter' => [
                'enabled'     => true,
                'card'        => $meta->twitterCard,
                'title'       => $meta->ogTitle,
                'description' => $meta->ogDescription,
                'image'       => $meta->ogImage,
            ],
        ];

        if (!$meta->structuredData->isEmpty()) {
            $payload['json_ld'] = ['enabled' => true, 'json' => $meta->structuredData->toJson()];
        }

        $this->seoRuntime->set($payload);
    }
}
