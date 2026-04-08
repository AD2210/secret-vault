<?php

declare(strict_types=1);

namespace App\Secrets;

use App\Entity\Secret;
use App\Security\VaultCipher;

final readonly class SecretPayloadCodec
{
    public function __construct(
        private VaultCipher $cipher,
    ) {
    }

    /**
     * @param array<string, scalar> $payload
     */
    public function encodeIntoSecret(Secret $secret, array $payload): void
    {
        if ([] === $payload) {
            $secret
                ->setPayloadEncrypted(null)
                ->setEncryptionKeyVersion(null);

            return;
        }

        $encrypted = $this->cipher->encrypt(json_encode($payload, JSON_THROW_ON_ERROR));

        $secret
            ->setPayloadEncrypted($encrypted->getPayload())
            ->setEncryptionKeyVersion($encrypted->getKeyId());
    }

    /**
     * @return array<string, string|null>
     */
    public function decode(Secret $secret): array
    {
        $payload = $this->cipher->decrypt($secret->getPayloadEncrypted(), $secret->getEncryptionKeyVersion());
        if (is_string($payload) && '' !== $payload) {
            try {
                /** @var array<string, scalar|null> $decoded */
                $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

                return array_map(
                    static fn (mixed $value): ?string => is_scalar($value) ? (string) $value : null,
                    $decoded,
                );
            } catch (\JsonException) {
            }
        }

        return array_filter([
            'secret_value' => $this->cipher->decrypt($secret->getPrivateSecretEncrypted(), $secret->getEncryptionKeyVersion()),
            'notes' => $this->cipher->decrypt($secret->getPublicSecretEncrypted(), $secret->getEncryptionKeyVersion()),
        ], static fn (?string $value): bool => null !== $value && '' !== $value);
    }

    public function rotate(Secret $secret): bool
    {
        $rotated = false;

        if ($this->cipher->needsRotation($secret->getPayloadEncrypted(), $secret->getEncryptionKeyVersion())) {
            $payload = $this->cipher->decrypt($secret->getPayloadEncrypted(), $secret->getEncryptionKeyVersion());
            $encrypted = $this->cipher->encrypt($payload);
            $secret
                ->setPayloadEncrypted($encrypted->getPayload())
                ->setEncryptionKeyVersion($encrypted->getKeyId());
            $rotated = true;
        }

        if ($this->cipher->needsRotation($secret->getPublicSecretEncrypted(), $secret->getEncryptionKeyVersion())) {
            $publicValue = $this->cipher->decrypt($secret->getPublicSecretEncrypted(), $secret->getEncryptionKeyVersion());
            $encrypted = $this->cipher->encrypt($publicValue);
            $secret
                ->setPublicSecretEncrypted($encrypted->getPayload())
                ->setEncryptionKeyVersion($encrypted->getKeyId());
            $rotated = true;
        }

        if ($this->cipher->needsRotation($secret->getPrivateSecretEncrypted(), $secret->getEncryptionKeyVersion())) {
            $privateValue = $this->cipher->decrypt($secret->getPrivateSecretEncrypted(), $secret->getEncryptionKeyVersion());
            $encrypted = $this->cipher->encrypt($privateValue);
            $secret
                ->setPrivateSecretEncrypted($encrypted->getPayload())
                ->setEncryptionKeyVersion($encrypted->getKeyId());
            $rotated = true;
        }

        return $rotated;
    }
}
