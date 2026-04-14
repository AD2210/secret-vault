<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\VaultTransitClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class VaultTransitClientTest extends TestCase
{
    public function testEncryptDecryptAndCurrentKeyVersion(): void
    {
        $client = new VaultTransitClient(
            new MockHttpClient([
                static function (string $method, string $url, array $options): MockResponse {
                    TestCase::assertSame('POST', $method);
                    TestCase::assertStringEndsWith('/v1/transit/encrypt/app-key', $url);
                    /** @var array{plaintext?: string} $body */
                    $body = json_decode((string) ($options['body'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
                    TestCase::assertSame('bmV3LWRhdGEta2V5', $body['plaintext'] ?? null);

                    return new MockResponse(json_encode([
                        'data' => ['ciphertext' => 'vault:v4:test-ciphertext'],
                    ], JSON_THROW_ON_ERROR));
                },
                static function (string $method, string $url, array $options): MockResponse {
                    TestCase::assertSame('POST', $method);
                    TestCase::assertStringEndsWith('/v1/transit/decrypt/app-key', $url);
                    /** @var array{ciphertext?: string} $body */
                    $body = json_decode((string) ($options['body'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
                    TestCase::assertSame('vault:v4:test-ciphertext', $body['ciphertext'] ?? null);

                    return new MockResponse(json_encode([
                        'data' => ['plaintext' => 'bmV3LWRhdGEta2V5'],
                    ], JSON_THROW_ON_ERROR));
                },
                static function (string $method, string $url): MockResponse {
                    TestCase::assertSame('GET', $method);
                    TestCase::assertStringEndsWith('/v1/transit/keys/app-key', $url);

                    return new MockResponse(json_encode([
                        'data' => ['latest_version' => 4],
                    ], JSON_THROW_ON_ERROR));
                },
            ]),
            'https://vault.test',
            'token',
            '',
        );

        self::assertSame('vault:v4:test-ciphertext', $client->encrypt('app-key', 'new-data-key'));
        self::assertSame('new-data-key', $client->decrypt('app-key', 'vault:v4:test-ciphertext'));
        self::assertSame(4, $client->currentKeyVersion('app-key'));
        self::assertSame(4, $client->ciphertextVersion('vault:v4:test-ciphertext'));
        self::assertNull($client->ciphertextVersion('invalid'));
    }

    public function testVaultErrorsAreSurfaced(): void
    {
        $client = new VaultTransitClient(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'errors' => ['permission denied'],
                ], JSON_THROW_ON_ERROR), ['http_code' => 403]),
            ]),
            'https://vault.test',
            'token',
            '',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Vault Transit error: permission denied');

        $client->encrypt('app-key', 'secret');
    }

    public function testTransportFailuresIncludeRequestContext(): void
    {
        $client = new VaultTransitClient(
            new MockHttpClient([
                static fn (string $method, string $url, array $options): never => throw new TransportException('Connection refused'),
            ]),
            'https://vault.test',
            'token',
            '',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Vault Transit request failed for POST https://vault.test/v1/transit/encrypt/app-key: Connection refused');

        $client->encrypt('app-key', 'secret');
    }
}
