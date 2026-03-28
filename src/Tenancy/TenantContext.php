<?php

declare(strict_types=1);

namespace App\Tenancy;

final class TenantContext
{
    private ?string $tenantSlug = null;

    public function setTenantSlug(?string $tenantSlug): void
    {
        $this->tenantSlug = null !== $tenantSlug && '' !== trim($tenantSlug) ? trim($tenantSlug) : null;
    }

    public function getTenantSlug(): ?string
    {
        return $this->tenantSlug;
    }

    public function requireTenantSlug(): string
    {
        if (null === $this->tenantSlug) {
            throw new \RuntimeException('Tenant slug is not available in the current request context.');
        }

        return $this->tenantSlug;
    }
}
