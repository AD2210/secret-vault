<?php

declare(strict_types=1);

namespace App\Tenancy;

final readonly class TenantDatabasePathResolver
{
    public function __construct(
        private string $projectDir,
    ) {
    }

    public function getDirectory(): string
    {
        return $this->projectDir.'/var/tenants';
    }

    public function resolvePath(string $tenantSlug): string
    {
        $normalized = mb_strtolower(trim($tenantSlug));
        $normalized = (string) preg_replace('/[^a-z0-9-]+/', '-', $normalized);
        $normalized = trim($normalized, '-');

        if ('' === $normalized) {
            throw new \InvalidArgumentException('Tenant slug cannot be empty.');
        }

        return $this->getDirectory().'/'.$normalized.'.sqlite';
    }
}
