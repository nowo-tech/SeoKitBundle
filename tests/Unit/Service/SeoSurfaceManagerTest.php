<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Nowo\SeoKitBundle\Service\SeoSurfaceManager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SeoSurfaceManagerTest extends TestCase
{
    public function testFindAndGetOrCreateDelegateToRepository(): void
    {
        $existing = new SeoSurface();
        $existing->setSurfaceKey('page:home')->setLocale('en');

        $repo = $this->createMock(SeoSurfaceRepository::class);
        $repo->expects(self::once())->method('findOneByKeyAndLocale')->with('page:home', 'en')->willReturn($existing);
        $repo->expects(self::once())->method('getOrCreate')->with('page:about', 'es')->willReturn($existing);

        $manager = new SeoSurfaceManager($repo);

        self::assertSame($existing, $manager->find('page:home', 'en'));
        self::assertSame($existing, $manager->getOrCreate('page:about', 'es'));
    }

    public function testSaveOrClearRemovesAnEmptyExistingRow(): void
    {
        $surface = new SeoSurface();
        $surface->setSurfaceKey('page:about');
        $surface->setLocale('es');
        (new ReflectionClass($surface))->getProperty('id')->setValue($surface, 7);

        $repo = $this->createMock(SeoSurfaceRepository::class);
        $repo->expects(self::once())->method('remove')->with($surface);
        $repo->expects(self::never())->method('save');

        $manager = new SeoSurfaceManager($repo);

        self::assertFalse($manager->saveOrClear($surface));
        self::assertTrue($manager->isEmpty($surface));
    }

    public function testSaveOrClearSkipsRemoveWhenEmptyAndNew(): void
    {
        $surface = new SeoSurface();
        $surface->setSurfaceKey('page:about')->setLocale('es');

        $repo = $this->createMock(SeoSurfaceRepository::class);
        $repo->expects(self::never())->method('remove');
        $repo->expects(self::never())->method('save');

        self::assertFalse((new SeoSurfaceManager($repo))->saveOrClear($surface));
    }

    public function testSaveOrClearPersistsWhenAnyFieldIsSet(): void
    {
        $surface = new SeoSurface();
        $surface->setSurfaceKey('page:about');
        $surface->setLocale('es');
        $surface->setMetaTitle('About');

        $repo = $this->createMock(SeoSurfaceRepository::class);
        $repo->expects(self::once())->method('save')->with($surface);
        $repo->expects(self::never())->method('remove');

        self::assertTrue((new SeoSurfaceManager($repo))->saveOrClear($surface));
    }
}
