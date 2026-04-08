<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class VaultKeyRing
{
    /**
     * @var array<string, VaultKey>
     */
    private array $keysById = [];

    private VaultKey $activeKey;

    public function __construct(
        #[Autowire('%env(string:VAULT_ACTIVE_KEY_ID)%')]
        string $activeKeyId,
        #[Autowire('%env(string:VAULT_KMS_KEYRING)%')]
        string $rawKeyRing,
    ) {
        $normalizedActiveKeyId = trim($activeKeyId);
        if ('' === $normalizedActiveKeyId) {
            throw new \InvalidArgumentException('VAULT_ACTIVE_KEY_ID must be a non-empty string.');
        }

        foreach ($this->parseKeyRing($rawKeyRing) as $keyId => $hexKey) {
            $this->keysById[$keyId] = new VaultKey($keyId, $this->normalizeHexKey($hexKey, $keyId));
        }

        if (!isset($this->keysById[$normalizedActiveKeyId])) {
            throw new \InvalidArgumentException(sprintf('Active vault key "%s" is missing from VAULT_KMS_KEYRING.', $normalizedActiveKeyId));
        }

        $this->activeKey = $this->keysById[$normalizedActiveKeyId];
    }

    public function active(): VaultKey
    {
        return $this->activeKey;
    }

    public function activeKeyId(): string
    {
        return $this->activeKey->getId();
    }

    public function get(string $keyId): ?VaultKey
    {
        return $this->keysById[$keyId] ?? null;
    }

    /**
     * @return list<string>
     */
    public function allKeyIds(): array
    {
        return array_keys($this->keysById);
    }

    /**
     * @return array<string, string>
     */
    private function parseKeyRing(string $rawKeyRing): array
    {
        $normalized = trim($rawKeyRing);
        if ('' === $normalized) {
            throw new \InvalidArgumentException('VAULT_KMS_KEYRING must not be empty.');
        }

        if (str_starts_with($normalized, '{')) {
            try {
                $decoded = json_decode($normalized, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $decoded = null;
            }

            if (is_array($decoded) && [] !== $decoded) {
                $parsed = [];
                foreach ($decoded as $keyId => $hexKey) {
                    if (!is_string($keyId) || !is_string($hexKey)) {
                        throw new \InvalidArgumentException('VAULT_KMS_KEYRING JSON entries must be string:string.');
                    }

                    $normalizedKeyId = trim($keyId);
                    if ('' === $normalizedKeyId) {
                        throw new \InvalidArgumentException('VAULT_KMS_KEYRING contains an empty key identifier.');
                    }

                    $parsed[$normalizedKeyId] = $hexKey;
                }

                return $parsed;
            }

            return $this->parseInlinePairs(trim($normalized, '{}'));
        }

        return $this->parseInlinePairs($normalized);
    }

    /**
     * @return array<string, string>
     */
    private function parseInlinePairs(string $rawPairs): array
    {
        $parsed = [];
        foreach (explode(',', $rawPairs) as $pair) {
            $pair = trim($pair);
            if ('' === $pair) {
                continue;
            }

            [$keyId, $hexKey] = array_pad(preg_split('/[:=]/', $pair, 2) ?: [], 2, null);
            if (!is_string($keyId) || !is_string($hexKey)) {
                throw new \InvalidArgumentException('VAULT_KMS_KEYRING must use the "key-id:key" format.');
            }

            $normalizedKeyId = trim($keyId);
            if ('' === $normalizedKeyId) {
                throw new \InvalidArgumentException('VAULT_KMS_KEYRING contains an empty key identifier.');
            }

            $parsed[$normalizedKeyId] = trim($hexKey);
        }

        if ([] === $parsed) {
            throw new \InvalidArgumentException('VAULT_KMS_KEYRING must define at least one key.');
        }

        return $parsed;
    }

    private function normalizeHexKey(string $hexKey, string $keyId): string
    {
        $normalizedKey = strtolower(trim($hexKey));
        if ('' === $normalizedKey || !ctype_xdigit($normalizedKey)) {
            throw new \InvalidArgumentException(sprintf('Vault key "%s" must be a non-empty hexadecimal string.', $keyId));
        }

        $binaryKey = sodium_hex2bin($normalizedKey);
        if (SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== strlen($binaryKey)) {
            throw new \InvalidArgumentException(sprintf('Vault key "%s" must be %d bytes long.', $keyId, SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        }

        return $binaryKey;
    }
}
