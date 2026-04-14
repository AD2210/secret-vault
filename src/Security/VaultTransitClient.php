<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class VaultTransitClient
{
    /**
     * @var array<string, int>
     */
    private array $cachedVersions = [];

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(string:VAULT_TRANSIT_ADDR)%')]
        private string $address,
        #[Autowire('%env(string:VAULT_TRANSIT_TOKEN)%')]
        private string $token,
        #[Autowire('%env(string:default::VAULT_TRANSIT_NAMESPACE)%')]
        private string $namespace = '',
    ) {
    }

    public function encrypt(string $keyName, string $plaintext): string
    {
        $data = $this->request(sprintf('/v1/transit/encrypt/%s', rawurlencode($keyName)), [
            'plaintext' => base64_encode($plaintext),
        ]);

        return $this->extractString($data, 'ciphertext');
    }

    public function decrypt(string $keyName, string $ciphertext): string
    {
        $data = $this->request(sprintf('/v1/transit/decrypt/%s', rawurlencode($keyName)), [
            'ciphertext' => $ciphertext,
        ]);

        $plaintext = base64_decode($this->extractString($data, 'plaintext'), true);
        if (false === $plaintext) {
            throw new \RuntimeException('Vault Transit returned an invalid plaintext.');
        }

        return $plaintext;
    }

    public function currentKeyVersion(string $keyName): int
    {
        if (isset($this->cachedVersions[$keyName])) {
            return $this->cachedVersions[$keyName];
        }

        $data = $this->request(sprintf('/v1/transit/keys/%s', rawurlencode($keyName)), null, 'GET');
        $version = $data['latest_version'] ?? null;
        if (!is_int($version) || $version < 1) {
            throw new \RuntimeException(sprintf('Vault Transit key "%s" does not expose a valid latest_version.', $keyName));
        }

        $this->cachedVersions[$keyName] = $version;

        return $version;
    }

    public function ciphertextVersion(string $ciphertext): ?int
    {
        if (1 !== preg_match('/^vault:v(\d+):/i', $ciphertext, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * @param array<string, scalar|null>|null $body
     * @return array<string, mixed>
     */
    private function request(string $path, ?array $body = null, string $method = 'POST'): array
    {
        $address = rtrim(trim($this->address), '/');
        $token = trim($this->token);
        if ('' === $address || '' === $token) {
            throw new \RuntimeException('Vault Transit is enabled but VAULT_TRANSIT_ADDR or VAULT_TRANSIT_TOKEN is missing.');
        }

        $headers = [
            'X-Vault-Token' => $token,
            'Accept' => 'application/json',
        ];
        if ('' !== trim($this->namespace)) {
            $headers['X-Vault-Namespace'] = trim($this->namespace);
        }

        $options = [
            'headers' => $headers,
        ];
        if (null !== $body) {
            $options['json'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, $address.$path, $options);
            $payload = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new \RuntimeException(sprintf(
                'Vault Transit request failed for %s %s: %s',
                $method,
                $address.$path,
                $exception->getMessage(),
            ), previous: $exception);
        }

        if (isset($payload['errors']) && is_array($payload['errors']) && [] !== $payload['errors']) {
            throw new \RuntimeException(sprintf('Vault Transit error: %s', implode(' ', array_map('strval', $payload['errors']))));
        }

        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            throw new \RuntimeException('Vault Transit response is missing its data payload.');
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || '' === trim($value)) {
            throw new \RuntimeException(sprintf('Vault Transit response is missing "%s".', $key));
        }

        return trim($value);
    }
}
