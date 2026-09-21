<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service\Persistence;

use Nowo\SeoKitBundle\Entity\SeoSiteSettings;
use Nowo\SeoKitBundle\Model\SeoSiteConfig;
use Nowo\SeoKitBundle\Repository\SeoSiteSettingsRepository;
use Nowo\SeoKitBundle\Service\Persistence\SeoSiteConfigProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class SeoSiteConfigProviderTest extends TestCase
{
    public function testGetSnapshotsRepositoryAndCachesInMemory(): void
    {
        $settings = new SeoSiteSettings();
        $settings->setSiteName('Nowo');
        $settings->setIndexable(true);
        $settings->setDefaultRobots('index, follow');
        $settings->setStreetAddress('Calle 1');
        $translation = $settings->translationFor('en');
        $translation->setDefaultTitle('Home');
        $translation->setDefaultDescription('Desc');
        $translation->setHomeTitle('Welcome');
        $translation->setOrganisationDescription('Org desc');

        $repo = $this->createMock(SeoSiteSettingsRepository::class);
        $repo->expects(self::once())->method('getOrCreate')->willReturn($settings);

        $provider = new SeoSiteConfigProvider($repo);
        $first    = $provider->get();
        $second   = $provider->get();

        self::assertSame($first, $second);
        self::assertSame('Nowo', $first->siteName);
        self::assertSame('Home', $first->titleFor('en'));
        self::assertSame('Desc', $first->descriptionFor('en'));
        self::assertSame('Welcome', $first->homeTitleFor('en'));
        self::assertSame('Org desc', $first->organisationDescriptionFor('en'));
        self::assertTrue($first->hasPostalAddress());
    }

    public function testGetUsesCacheHitAndRefreshDeletesCache(): void
    {
        $cached = new SeoSiteConfig(
            siteName: 'Cached',
            titleTemplate: '%page%',
            indexable: true,
            robots: 'index, follow',
            openGraphImage: null,
            twitterSite: null,
            organisationLegalName: null,
            organisationLogo: null,
            contactEmail: null,
            contactPhone: null,
            streetAddress: null,
            postalCode: null,
            addressLocality: null,
            addressRegion: null,
            addressCountry: null,
            socialProfiles: [],
            googleSiteVerification: null,
            bingSiteVerification: null,
            byLocale: [],
        );

        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn($cached);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($item);
        $cache->expects(self::once())->method('deleteItem');

        $repo = $this->createMock(SeoSiteSettingsRepository::class);
        $repo->expects(self::never())->method('getOrCreate');

        $provider = new SeoSiteConfigProvider($repo, $cache);
        self::assertSame('Cached', $provider->get()->siteName);

        $provider->refresh();
        $provider->reset();
    }

    public function testGetResnapshotsCorruptCacheHitWithoutRewriting(): void
    {
        $settings = new SeoSiteSettings();
        $settings->setSiteName('Resnapshot');

        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn('not-a-config');
        $item->expects(self::never())->method('set');

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($item);
        $cache->expects(self::never())->method('save');

        $repo = $this->createMock(SeoSiteSettingsRepository::class);
        $repo->expects(self::once())->method('getOrCreate')->willReturn($settings);

        $provider = new SeoSiteConfigProvider($repo, $cache);
        self::assertSame('Resnapshot', $provider->get()->siteName);
    }

    public function testGetStoresMissInCache(): void
    {
        $settings = new SeoSiteSettings();
        $settings->setSiteName('Fresh');

        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);
        $item->expects(self::once())->method('set')->with(self::isInstanceOf(SeoSiteConfig::class));

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($item);
        $cache->expects(self::once())->method('save')->with($item);

        $repo = $this->createMock(SeoSiteSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);

        $provider = new SeoSiteConfigProvider($repo, $cache);
        self::assertSame('Fresh', $provider->get()->siteName);
    }

    public function testGetFallsBackWhenRepositoryThrows(): void
    {
        $repo = $this->createMock(SeoSiteSettingsRepository::class);
        $repo->method('getOrCreate')->willThrowException(new RuntimeException('missing table'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $provider = new SeoSiteConfigProvider(
            $repo,
            null,
            $logger,
            'noindex, follow',
            'fallback@nowo.tech',
            'Fallback Site',
        );

        $config = $provider->get();
        self::assertSame('Fallback Site', $config->siteName);
        self::assertFalse($config->indexable);
        self::assertSame('fallback@nowo.tech', $config->contactEmail);
    }
}
