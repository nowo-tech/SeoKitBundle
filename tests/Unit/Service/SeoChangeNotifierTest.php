<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Service;

use Nowo\SeoKitBundle\Event\SeoSiteSettingsChangedEvent;
use Nowo\SeoKitBundle\Event\SeoSurfaceChangedEvent;
use Nowo\SeoKitBundle\Service\SeoChangeNotifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(SeoChangeNotifier::class)]
#[CoversClass(SeoSurfaceChangedEvent::class)]
#[CoversClass(SeoSiteSettingsChangedEvent::class)]
final class SeoChangeNotifierTest extends TestCase
{
    public function testDispatchesIdOnlyEventsWithTheActor(): void
    {
        $events     = [];
        $dispatcher = new EventDispatcher();
        foreach ([SeoSurfaceChangedEvent::class, SeoSiteSettingsChangedEvent::class] as $name) {
            $dispatcher->addListener($name, static function (object $event) use (&$events): void {
                $events[] = $event;
            });
        }
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('editor', null), 'main'));
        $notifier = new SeoChangeNotifier($dispatcher, $tokens);

        $notifier->surfaceChanged('page:home', 'en', 7, true);
        $notifier->settingsChanged();

        self::assertCount(2, $events);
        $surface = $events[0];
        self::assertInstanceOf(SeoSurfaceChangedEvent::class, $surface);
        self::assertSame(['page:home', 'en', 7, true, 'editor'], [$surface->surfaceKey, $surface->locale, $surface->surfaceId, $surface->deleted, $surface->actorId]);
        $settings = $events[1];
        self::assertInstanceOf(SeoSiteSettingsChangedEvent::class, $settings);
        self::assertSame('editor', $settings->actorId);
    }

    public function testWithoutTokenTheActorIsNullAndWithoutDispatcherNothingHappens(): void
    {
        $seen       = null;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(SeoSurfaceChangedEvent::class, static function (SeoSurfaceChangedEvent $event) use (&$seen): void {
            $seen = $event;
        });

        (new SeoChangeNotifier($dispatcher, new TokenStorage()))->surfaceChanged('page:about', 'es', null, false);
        (new SeoChangeNotifier())->settingsChanged();

        self::assertInstanceOf(SeoSurfaceChangedEvent::class, $seen);
        self::assertNull($seen->actorId);
        self::assertFalse($seen->deleted);
    }
}
