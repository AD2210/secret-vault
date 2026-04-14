<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class VaultCipher
{
    private const string LOCAL_PAYLOAD_PREFIX = 'v1';
    private const string TRANSIT_PAYLOAD_PREFIX = 'v2';

    private readonly string $legacyKey;

    public function __construct(
        private readonly VaultKeyRing $keyRing,
        private readonly VaultTransitClient $transit,
        #[Autowire('%env(string:VAULT_ENCRYPTION_KEY)%')]
        string $hexKey,
        #[Autowire('%env(string:VAULT_KMS_PROVIDER)%')]
        private readonly string $provider,
        #[Autowire('%env(string:default::VAULT_TRANSIT_KEY_NAME)%')]
        private readonly string $transitKeyName = '',
    ) {
        $this->legacyKey = $this->normalizeHexKey($hexKey);
    }

    public function encrypt(?string $plaintext): EncryptedValue
    {
        if (null === $plaintext || '' === trim($plaintext)) {
            return new EncryptedValue(null, null);
        }

        if ($this->isTransitProvider()) {
            return $this->encryptWithTransit($plaintext);
        }

        $key = $this->keyRing->active();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key->getBinaryKey());

        return new EncryptedValue(
            sprintf('%s.%s.%s', self::LOCAL_PAYLOAD_PREFIX, $key->getId(), base64_encode($nonce.$ciphertext)),
            $key->getId(),
        );
    }

    public function decrypt(?string $payload, ?string $keyId = null): ?string
    {
        if (null === $payload || '' === trim($payload)) {
            return null;
        }

        if (str_starts_with(trim($payload), self::TRANSIT_PAYLOAD_PREFIX.'.')) {
            return $this->decryptTransitPayload(trim($payload));
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

        if (str_starts_with($payload, self::TRANSIT_PAYLOAD_PREFIX.'.')) {
            return $this->transitPayloadNeedsRotation($payload);
        }

        if (!str_starts_with($payload, self::LOCAL_PAYLOAD_PREFIX.'.')) {
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
        if (str_starts_with($normalizedPayload, self::LOCAL_PAYLOAD_PREFIX.'.')) {
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

    private function isTransitProvider(): bool
    {
        return 'transit' === strtolower(trim($this->provider));
    }

    private function encryptWithTransit(string $plaintext): EncryptedValue
    {
        $keyName = trim($this->transitKeyName);
        if ('' === $keyName) {
            throw new \RuntimeException('VAULT_TRANSIT_KEY_NAME is required when VAULT_KMS_PROVIDER=transit.');
        }

        $dataKey = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $wrappedKey = $this->transit->encrypt($keyName, $dataKey);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $dataKey);

        $payload = base64_encode(json_encode([
            'wrapped_key' => $wrappedKey,
            'nonce' => base64_encode($nonce),
            'ciphertext' => base64_encode($ciphertext),
        ], JSON_THROW_ON_ERROR));

        return new EncryptedValue(
            sprintf('%s.%s.%s', self::TRANSIT_PAYLOAD_PREFIX, $keyName, $payload),
            $keyName,
        );
    }

    private function decryptTransitPayload(string $payload): string
    {
        $parts = explode('.', $payload, 3);
        if (3 !== count($parts)) {
            throw new \RuntimeException('Encrypted vault payload is invalid.');
        }

        $keyName = trim($parts[1]);
        $decoded = base64_decode($parts[2], true);
        if (false === $decoded) {
            throw new \RuntimeException('Encrypted vault payload is invalid.');
        }

        try {
            /** @var array<string, mixed> $package */
            $package = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Encrypted vault payload is invalid.', previous: $exception);
        }

        $wrappedKey = $package['wrapped_key'] ?? null;
        $nonce = isset($package['nonce']) && is_string($package['nonce']) ? base64_decode($package['nonce'], true) : false;
        $ciphertext = isset($package['ciphertext']) && is_string($package['ciphertext']) ? base64_decode($package['ciphertext'], true) : false;
        if (!is_string($wrappedKey) || false === $nonce || false === $ciphertext) {
            throw new \RuntimeException('Encrypted vault payload is invalid.');
        }

        $dataKey = $this->transit->decrypt($keyName, $wrappedKey);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $dataKey);
        if (false === $plaintext) {
            throw new \RuntimeException('Unable to decrypt vault payload.');
        }

        return $plaintext;
    }

    private function transitPayloadNeedsRotation(string $payload): bool
    {
        if (!$this->isTransitProvider()) {
            return true;
        }

        $parts = explode('.', $payload, 3);
        if (3 !== count($parts)) {
            return true;
        }

        $keyName = trim($parts[1]);
        if ('' === $keyName || $keyName !== trim($this->transitKeyName)) {
            return true;
        }

        $decoded = base64_decode($parts[2], true);
        if (false === $decoded) {
            return true;
        }

        try {
            /** @var array<string, mixed> $package */
            $package = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return true;
        }

        $wrappedKey = $package['wrapped_key'] ?? null;
        if (!is_string($wrappedKey) || '' === trim($wrappedKey)) {
            return true;
        }

        $ciphertextVersion = $this->transit->ciphertextVersion($wrappedKey);
        if (null === $ciphertextVersion) {
            return true;
        }

        return $ciphertextVersion < $this->transit->currentKeyVersion($keyName);
    }
}
