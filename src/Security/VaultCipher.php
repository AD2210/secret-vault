<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class VaultCipher
{
    private const string PAYLOAD_PREFIX = 'v1';

    private readonly string $legacyKey;

    public function __construct(
        private readonly VaultKeyRing $keyRing,
        #[Autowire('%env(string:VAULT_ENCRYPTION_KEY)%')]
        string $hexKey,
    ) {
        $this->legacyKey = $this->normalizeHexKey($hexKey);
    }

    public function encrypt(?string $plaintext): EncryptedValue
    {
        if (null === $plaintext || '' === trim($plaintext)) {
            return new EncryptedValue(null, null);
        }

        $key = $this->keyRing->active();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key->getBinaryKey());

        return new EncryptedValue(
            sprintf('%s.%s.%s', self::PAYLOAD_PREFIX, $key->getId(), base64_encode($nonce.$ciphertext)),
            $key->getId(),
        );
    }

    public function decrypt(?string $payload, ?string $keyId = null): ?string
    {
        if (null === $payload || '' === trim($payload)) {
            return null;
        }

        [$resolvedKey, $encodedPayload] = $this->resolveKeyAndPayload($payload, $keyId);
        $decoded = base64_decode($encodedPayload, true);
        if (false === $decoded || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Encrypted vault payload is invalid.');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $resolvedKey);
        if (false === $plaintext) {
            throw new \RuntimeException('Unable to decrypt vault payload.');
        }

        return $plaintext;
    }

    public function needsRotation(?string $payload, ?string $keyId = null): bool
    {
        if (null === $payload || '' === trim($payload)) {
            return false;
        }

        if (!str_starts_with($payload, self::PAYLOAD_PREFIX.'.')) {
            return true;
        }

        $parts = explode('.', $payload, 3);
        if (3 !== count($parts)) {
            return true;
        }

        $payloadKeyId = trim($parts[1]);

        return $payloadKeyId !== $this->keyRing->activeKeyId() || $keyId !== $payloadKeyId;
    }

    private function normalizeHexKey(string $hexKey): string
    {
        $normalizedKey = strtolower(trim($hexKey));
        if ('' === $normalizedKey || !ctype_xdigit($normalizedKey)) {
            throw new \InvalidArgumentException('VAULT_ENCRYPTION_KEY must be a non-empty hexadecimal string.');
        }

        $binaryKey = sodium_hex2bin($normalizedKey);
        if (SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== strlen($binaryKey)) {
            throw new \InvalidArgumentException(sprintf('VAULT_ENCRYPTION_KEY must be %d bytes long.', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        }

        return $binaryKey;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveKeyAndPayload(string $payload, ?string $keyId): array
    {
        $normalizedPayload = trim($payload);
        if (str_starts_with($normalizedPayload, self::PAYLOAD_PREFIX.'.')) {
            $parts = explode('.', $normalizedPayload, 3);
            if (3 !== count($parts)) {
                throw new \RuntimeException('Encrypted vault payload is invalid.');
            }

            $prefixedKey = $this->keyRing->get($parts[1]);
            if (!$prefixedKey instanceof VaultKey) {
                throw new \RuntimeException(sprintf('Unknown vault key "%s".', $parts[1]));
            }

            return [$prefixedKey->getBinaryKey(), $parts[2]];
        }

        if (null !== $keyId && '' !== trim($keyId)) {
            $storedKey = $this->keyRing->get(trim($keyId));
            if ($storedKey instanceof VaultKey) {
                return [$storedKey->getBinaryKey(), $normalizedPayload];
            }
        }

        return [$this->legacyKey, $normalizedPayload];
    }
}
