<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Entity\SeoSurface;
use Nowo\SeoKitBundle\Event\SeoSurfaceChangedEvent;
use Nowo\SeoKitBundle\Model\SeoSurfaceData;
use Nowo\SeoKitBundle\Repository\SeoSurfaceRepository;
use Nowo\SeoKitBundle\Service\SeoChangeNotifier;
use Nowo\SeoKitBundle\Service\SeoSurfaceManager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventDispatcher;

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

    /** Since 1.22: saveOrClear() dispatches SeoSurfaceChangedEvent after each write, nothing when nothing was written. */
    public function testSaveOrClearDispatchesChangeEventsAfterTheWrite(): void
    {
        $events     = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(SeoSurfaceChangedEvent::class, static function (SeoSurfaceChangedEvent $event) use (&$events): void {
            $events[] = [$event->surfaceKey, $event->locale, $event->surfaceId, $event->deleted];
        });
        $surface = (new SeoSurface())->setSurfaceKey('page:about')->setLocale('es')->setMetaTitle('About');
        $repo    = $this->createMock(SeoSurfaceRepository::class);
        $repo->method('save')->willReturnCallback(static function (SeoSurface $saved): void {
            (new ReflectionClass($saved))->getProperty('id')->setValue($saved, 9);
        });
        $manager = new SeoSurfaceManager($repo, new SeoChangeNotifier($dispatcher));

        $manager->saveOrClear($surface);
        $manager->saveOrClear($surface->setMetaTitle(null));
        $manager->saveOrClear((new SeoSurface())->setSurfaceKey('page:new')->setLocale('es'));

        self::assertSame([['page:about', 'es', 9, false], ['page:about', 'es', 9, true]], $events);
    }

    /** Since 1.24.0: the entity-free store maps the entity to SeoSurfaceData. */
    public function testFindDataMapsTheStoredRow(): void
    {
        $stored = (new SeoSurface())->setSurfaceKey('page:home')->setLocale('en')->setMetaTitle('Home')
            ->setMetaDescription('Desc')->setMetaRobots('noindex')->setCanonicalOverride('/c')->setOpenGraphImage('/og.png')
            ->setStructuredDataExtra(['@type' => 'WebPage']);
        $repo = $this->createMock(SeoSurfaceRepository::class);
        $repo->method('findOneByKeyAndLocale')->willReturnMap([['page:home', 'en', $stored], ['page:none', 'en', null]]);
        $manager = new SeoSurfaceManager($repo);

        self::assertEquals(
            new SeoSurfaceData('page:home', 'en', 'Home', 'Desc', 'noindex', '/c', '/og.png', ['@type' => 'WebPage']),
            $manager->findData('page:home', 'en'),
        );
        self::assertNull($manager->findData('page:none', 'en'));
    }

    public function testSaveDataUpdatesTheStoredRowOrCreatesOne(): void
    {
        $stored = (new SeoSurface())->setSurfaceKey('page:home')->setLocale('en')->setMetaTitle('Old')->setMetaRobots('noindex');
        (new ReflectionClass($stored))->getProperty('id')->setValue($stored, 3);
        $saved = [];
        $repo  = $this->createMock(SeoSurfaceRepository::class);
        $repo->method('findOneByKeyAndLocale')->willReturnMap([['page:home', 'en', $stored], ['page:new', 'es', null]]);
        $repo->expects(self::never())->method('getOrCreate');
        $repo->expects(self::exactly(2))->method('save')->willReturnCallback(static function (SeoSurface $surface) use (&$saved): void {
            $saved[] = $surface;
        });
        $manager = new SeoSurfaceManager($repo);

        self::assertTrue($manager->save(new SeoSurfaceData('page:home', 'en', 'New')));
        self::assertTrue($manager->save(new SeoSurfaceData('page:new', 'es', metaDescription: 'Nueva')));

        self::assertSame($stored, $saved[0]);
        self::assertSame('New', $stored->getMetaTitle());
        self::assertNull($stored->getMetaRobots(), 'save() replaces every field: a null clears it.');
        self::assertSame(['page:new', 'es', 'Nueva'], [$saved[1]->getSurfaceKey(), $saved[1]->getLocale(), $saved[1]->getMetaDescription()]);
    }

    public function testSaveEmptyDataRemovesTheRowAndNeverWritesAnEmptyOne(): void
    {
        $stored = (new SeoSurface())->setSurfaceKey('page:home')->setLocale('en')->setMetaTitle('Old');
        (new ReflectionClass($stored))->getProperty('id')->setValue($stored, 4);
        $repo = $this->createMock(SeoSurfaceRepository::class);
        $repo->method('findOneByKeyAndLocale')->willReturnMap([['page:home', 'en', $stored], ['page:new', 'en', null]]);
        $repo->expects(self::never())->method('save');
        $repo->expects(self::once())->method('remove')->with($stored);
        $manager = new SeoSurfaceManager($repo);

        self::assertFalse($manager->save(new SeoSurfaceData('page:home', 'en')));
        self::assertFalse($manager->save(new SeoSurfaceData('page:new', 'en')));
    }

    public function testClearRemovesAStoredRowAndDispatchesTheDeleteEvent(): void
    {
        $events     = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(SeoSurfaceChangedEvent::class, static function (SeoSurfaceChangedEvent $event) use (&$events): void {
            $events[] = [$event->surfaceKey, $event->locale, $event->surfaceId, $event->deleted];
        });
        $stored = (new SeoSurface())->setSurfaceKey('page:home')->setLocale('en')->setMetaTitle('Old');
        (new ReflectionClass($stored))->getProperty('id')->setValue($stored, 5);
        $unsaved = (new SeoSurface())->setSurfaceKey('page:ghost')->setLocale('en');
        $repo    = $this->createMock(SeoSurfaceRepository::class);
        $repo->method('findOneByKeyAndLocale')->willReturnMap([
            ['page:home', 'en', $stored],
            ['page:none', 'en', null],
            ['page:ghost', 'en', $unsaved],
        ]);
        $repo->expects(self::once())->method('remove')->with($stored);
        $manager = new SeoSurfaceManager($repo, new SeoChangeNotifier($dispatcher));

        self::assertTrue($manager->clear('page:home', 'en'));
        self::assertFalse($manager->clear('page:none', 'en'));
        self::assertFalse($manager->clear('page:ghost', 'en'));
        self::assertSame([['page:home', 'en', 5, true]], $events);
    }
}
