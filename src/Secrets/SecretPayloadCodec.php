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
    public function encode(array $payload): ?string
    {
        if ([] === $payload) {
            return null;
        }

        return $this->cipher->encrypt(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, string|null>
     */
    public function decode(Secret $secret): array
    {
        $payload = $this->cipher->decrypt($secret->getPayloadEncrypted());
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
            'secret_value' => $this->cipher->decrypt($secret->getPrivateSecretEncrypted()),
            'notes' => $this->cipher->decrypt($secret->getPublicSecretEncrypted()),
        ], static fn (?string $value): bool => null !== $value && '' !== $value);
    }
}
