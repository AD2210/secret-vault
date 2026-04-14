<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\VaultCipher;
use App\Security\VaultKeyRing;
use App\Security\VaultTransitClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class VaultCipherTest extends TestCase
{
    private const string KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
    private const string KEY_ID = 'test-2026-04';
    private const string TRANSIT_KEY = 'client-secret-vault';

    public function testLocalEncryptDecryptRoundTrip(): void
    {
        $cipher = new VaultCipher(
            $this->createKeyRing(),
            $this->createTransitClient([]),
            self::KEY,
            'local',
        );

        $encrypted = $cipher->encrypt("ssh-rsa AAAA\nline-2");

        self::assertSame(self::KEY_ID, $encrypted->getKeyId());
        self::assertNotNull($encrypted->getPayload());
        self::assertStringStartsWith('v1.'.self::KEY_ID.'.', $encrypted->getPayload());
        self::assertSame("ssh-rsa AAAA\nline-2", $cipher->decrypt($encrypted->getPayload(), $encrypted->getKeyId()));
    }

    public function testBlankValuesReturnEmptyEncryptedValueAndNullDecrypt(): void
    {
        $cipher = new VaultCipher(
            $this->createKeyRing(),
            $this->createTransitClient([]),
            self::KEY,
            'local',
        );

        $encrypted = $cipher->encrypt('   ');

        self::assertNull($encrypted->getPayload());
        self::assertNull($encrypted->getKeyId());
        self::assertNull($cipher->decrypt(null));
    }

    public function testTransitEncryptDecryptRoundTrip(): void
    {
        $wrappedKey = 'vault:v3:wrapped-data-key';
        $dataKey = null;
        $cipher = new VaultCipher(
            $this->createKeyRing(),
            $this->createTransitClient([
                static function (string $method, string $url, array $options) use (&$dataKey, $wrappedKey): MockResponse {
                    TestCase::assertSame('POST', $method);
                    TestCase::assertStringEndsWith('/v1/transit/encrypt/'.rawurlencode(self::TRANSIT_KEY), $url);
                    /** @var array{plaintext?: string} $body */
                    $body = json_decode((string) ($options['body'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
                    $dataKey = base64_decode((string) ($body['plaintext'] ?? ''), true);
                    TestCase::assertSame(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen((string) $dataKey));

                    return new MockResponse(json_encode([
                        'data' => ['ciphertext' => $wrappedKey],
                    ], JSON_THROW_ON_ERROR));
                },
                static function (string $method, string $url, array $options) use (&$dataKey, $wrappedKey): MockResponse {
                    TestCase::assertSame('POST', $method);
                    TestCase::assertStringEndsWith('/v1/transit/decrypt/'.rawurlencode(self::TRANSIT_KEY), $url);
                    /** @var array{ciphertext?: string} $body */
                    $body = json_decode((string) ($options['body'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
                    TestCase::assertSame($wrappedKey, $body['ciphertext'] ?? null);

                    return new MockResponse(json_encode([
                        'data' => ['plaintext' => base64_encode((string) $dataKey)],
                    ], JSON_THROW_ON_ERROR));
                },
            ]),
            self::KEY,
            'transit',
            self::TRANSIT_KEY,
        );

        $encrypted = $cipher->encrypt('super-secret');

        self::assertSame(self::TRANSIT_KEY, $encrypted->getKeyId());
        self::assertNotNull($encrypted->getPayload());
        self::assertStringStartsWith('v2.'.self::TRANSIT_KEY.'.', $encrypted->getPayload());
        self::assertSame('super-secret', $cipher->decrypt($encrypted->getPayload(), $encrypted->getKeyId()));
    }

    public function testTransitPayloadNeedsRotationWhenWrappedKeyVersionIsOutdated(): void
    {
        $cipher = new VaultCipher(
            $this->createKeyRing(),
            $this->createTransitClient([
                new MockResponse(json_encode([
                    'data' => ['ciphertext' => 'vault:v2:wrapped-data-key'],
                ], JSON_THROW_ON_ERROR)),
                new MockResponse(json_encode([
                    'data' => ['latest_version' => 3],
                ], JSON_THROW_ON_ERROR)),
            ]),
            self::KEY,
            'transit',
            self::TRANSIT_KEY,
        );

        $encrypted = $cipher->encrypt('rotate-me');

        self::assertNotNull($encrypted->getPayload());
        self::assertTrue($cipher->needsRotation($encrypted->getPayload(), $encrypted->getKeyId()));
    }

    private function createKeyRing(): VaultKeyRing
    {
        return new VaultKeyRing(self::KEY_ID, self::KEY_ID.':'.self::KEY);
    }

    /**
     * @param array<int, MockResponse|callable> $responses
     */
    private function createTransitClient(array $responses): VaultTransitClient
    {
        return new VaultTransitClient(
            new MockHttpClient($responses),
            'https://vault.test',
            'token',
            '',
        );
    }
}
