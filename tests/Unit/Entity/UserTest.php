<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testUserExposesTotpConfigurationWhenPrepared(): void
    {
        $user = new User('Ada@example.com', 'Ada', 'Lovelace');
        $user->prepareTotp('JBSWY3DPEHPK3PXP');

        self::assertFalse($user->isTotpAuthenticationEnabled());
        self::assertSame('ada@example.com', $user->getUserIdentifier());
        self::assertSame('Ada Lovelace', $user->getDisplayName());
        self::assertNotNull($user->getTotpAuthenticationConfiguration());
    }

    public function testEnableAndDisableTotp(): void
    {
        $user = new User('owner@example.com', 'Owner', 'Vault');
        $user->prepareTotp('JBSWY3DPEHPK3PXP');
        $user->enableTotp();

        self::assertTrue($user->isTotpAuthenticationEnabled());

        $user->disableTotp();

        self::assertFalse($user->isTotpAuthenticationEnabled());
        self::assertNull($user->getTotpAuthenticationConfiguration());
    }
}
