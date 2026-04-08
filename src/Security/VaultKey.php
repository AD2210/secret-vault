<?php

declare(strict_types=1);

namespace App\Security;

final readonly class VaultKey
{
    public function __construct(
        private string $id,
        private string $binaryKey,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getBinaryKey(): string
    {
        return $this->binaryKey;
    }
}
