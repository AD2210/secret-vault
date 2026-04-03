<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Entity\User;

final readonly class TenantUrlGenerator
{
    public function __construct(
        private string $defaultUri,
    ) {
    }

    public function generateTenantDashboardUrlForUser(User $user): ?string
    {
        $tenantSlug = $user->getTenantSlug();
        if (null === $tenantSlug) {
            return null;
        }

        return $this->generateTenantUrl($tenantSlug, '/');
    }

    public function generateTenantUrl(string $tenantSlug, string $path = '/', array $query = []): string
    {
        $parts = parse_url($this->defaultUri);
        $scheme = is_string($parts['scheme'] ?? null) && '' !== $parts['scheme'] ? $parts['scheme'] : 'https';
        $host = $parts['host'] ?? null;
        if (!is_string($host) || '' === $host) {
            throw new \RuntimeException('DEFAULT_URI must contain a valid host to generate tenant URLs.');
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $basePath = rtrim((string) ($parts['path'] ?? ''), '/');
        $normalizedPath = '/'.ltrim($path, '/');
        $url = sprintf('%s://%s.%s%s%s%s', $scheme, $tenantSlug, $host, $port, $basePath, $normalizedPath);

        if ([] !== $query) {
            $url .= '?'.http_build_query($query);
        }

        return $url;
    }
}
