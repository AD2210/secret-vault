<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class VaultCipher
{
    private readonly string $key;

    public function __construct(
        #[Autowire('%env(string:VAULT_ENCRYPTION_KEY)%')]
        string $hexKey,
    ) {
        $normalizedKey = strtolower(trim($hexKey));
        if ('' === $normalizedKey || !ctype_xdigit($normalizedKey)) {
            throw new \InvalidArgumentException('VAULT_ENCRYPTION_KEY must be a non-empty hexadecimal string.');
        }

        $binaryKey = sodium_hex2bin($normalizedKey);
        if (SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== strlen($binaryKey)) {
            throw new \InvalidArgumentException(sprintf('VAULT_ENCRYPTION_KEY must be %d bytes long.', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        }

        $this->key = $binaryKey;
    }

    public function encrypt(?string $plaintext): ?string
    {
        if (null === $plaintext || '' === trim($plaintext)) {
            return null;
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return base64_encode($nonce.$ciphertext);
    }

    public function decrypt(?string $payload): ?string
    {
        if (null === $payload || '' === trim($payload)) {
            return null;
        }

        $decoded = base64_decode($payload, true);
        if (false === $decoded || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Encrypted vault payload is invalid.');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);
        if (false === $plaintext) {
            throw new \RuntimeException('Unable to decrypt vault payload.');
        }

        return $plaintext;
    }
}
