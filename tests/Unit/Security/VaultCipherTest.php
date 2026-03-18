<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\VaultCipher;
use PHPUnit\Framework\TestCase;

final class VaultCipherTest extends TestCase
{
    private const string KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testEncryptDecryptRoundTrip(): void
    {
        $cipher = new VaultCipher(self::KEY);

        $encrypted = $cipher->encrypt("ssh-rsa AAAA\nline-2");

        self::assertNotNull($encrypted);
        self::assertNotSame("ssh-rsa AAAA\nline-2", $encrypted);
        self::assertSame("ssh-rsa AAAA\nline-2", $cipher->decrypt($encrypted));
    }

    public function testBlankValuesReturnNull(): void
    {
        $cipher = new VaultCipher(self::KEY);

        self::assertNull($cipher->encrypt('   '));
        self::assertNull($cipher->decrypt(null));
    }
}
