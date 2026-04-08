<?php

declare(strict_types=1);

namespace App\Security;

final readonly class EncryptedValue
{
    public function __construct(
        private ?string $payload,
        private ?string $keyId,
    ) {
    }

    public function getPayload(): ?string
    {
        return $this->payload;
    }

    public function getKeyId(): ?string
    {
        return $this->keyId;
    }
}
