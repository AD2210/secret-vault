<?php

declare(strict_types=1);

namespace App\Tenancy;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final class TenantDatabaseSwitcher
{
    private readonly array $baseParams;
    private ?string $currentTenantSlug = null;
    private bool $usingBaseDatabase = true;

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager,
        private readonly TenantDatabasePathResolver $pathResolver,
    ) {
        $this->baseParams = $connection->getParams();
    }

    public function switchToTenant(string $tenantSlug): string
    {
        $path = $this->pathResolver->resolvePath($tenantSlug);
        $this->applyPath($path);
        $this->currentTenantSlug = $tenantSlug;
        $this->usingBaseDatabase = false;

        return $path;
    }

    public function resetToBaseDatabase(): void
    {
        $this->applyParams($this->baseParams);
        $this->currentTenantSlug = null;
        $this->usingBaseDatabase = true;
    }

    public function isUsingBaseDatabase(): bool
    {
        return $this->usingBaseDatabase;
    }

    public function getCurrentTenantSlug(): ?string
    {
        return $this->currentTenantSlug;
    }

    private function applyPath(string $path): void
    {
        $params = [
            'driver' => 'pdo_sqlite',
            'path' => $path,
        ];

        foreach (['charset', 'driverOptions', 'defaultTableOptions', 'idle_connection_ttl'] as $key) {
            if (array_key_exists($key, $this->baseParams)) {
                $params[$key] = $this->baseParams[$key];
            }
        }

        $this->applyParams($params);
    }

    private function applyParams(array $params): void
    {
        $this->entityManager->clear();
        if ($this->connection->isConnected()) {
            $this->connection->close();
        }

        $ref = new \ReflectionClass($this->connection);

        $paramsProperty = $ref->getProperty('params');
        $paramsProperty->setValue($this->connection, $params);

        $connProperty = $ref->getProperty('_conn');
        $connProperty->setValue($this->connection, null);
    }
}
