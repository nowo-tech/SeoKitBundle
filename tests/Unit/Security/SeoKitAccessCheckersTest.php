<?php

declare(strict_types=1);

namespace Nowo\SeoKitBundle\Tests\Unit\Security;

use Nowo\SeoKitBundle\Security\AllowAllSeoKitAccessChecker;
use Nowo\SeoKitBundle\Security\ConfigurableSeoKitAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use function in_array;

final class SeoKitAccessCheckersTest extends TestCase
{
    public function testRoleCheckerGrantsWhenAnyRoleIsGranted(): void
    {
        $checker = new ConfigurableSeoKitAccessChecker($this->authorization(['ROLE_SEO']), ['ROLE_ADMIN', 'ROLE_SEO']);

        self::assertTrue($checker->canAccess());
    }

    public function testRoleCheckerDeniesWithoutAGrantedRole(): void
    {
        $checker = new ConfigurableSeoKitAccessChecker($this->authorization(['ROLE_USER']));

        self::assertFalse($checker->canAccess());
    }

    public function testRoleCheckerFailsClosedWithAnEmptyRoleList(): void
    {
        $checker = new ConfigurableSeoKitAccessChecker($this->authorization(['ROLE_ADMIN']), []);

        self::assertFalse($checker->canAccess());
    }

    public function testAllowAllCheckerGrantsEverybody(): void
    {
        self::assertTrue((new AllowAllSeoKitAccessChecker())->canAccess());
    }

    /**
     * @param list<string> $granted
     */
    private function authorization(array $granted): AuthorizationCheckerInterface
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturnCallback(static fn (mixed $attribute): bool => in_array($attribute, $granted, true));

        return $auth;
    }
}
