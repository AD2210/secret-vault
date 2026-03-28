<?php

declare(strict_types=1);

namespace App\Tenancy;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final class TenantDatabaseSwitcher
{
    private readonly array $baseParams;

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

        return $path;
    }

    public function resetToBaseDatabase(): void
    {
        $this->applyParams($this->baseParams);
    }

    private function applyPath(string $path): void
    {
        $params = $this->baseParams;
        unset($params['url'], $params['dbname'], $params['memory']);
        $params['path'] = $path;

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
