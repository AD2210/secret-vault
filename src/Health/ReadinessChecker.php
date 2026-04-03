<?php

declare(strict_types=1);

namespace App\Health;

use App\Tenancy\TenantDatabasePathResolver;
use Doctrine\DBAL\Connection;

final readonly class ReadinessChecker
{
    public function __construct(
        private Connection $connection,
        private TenantDatabasePathResolver $tenantPaths,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getFailures(): array
    {
        $failures = [];

        try {
            $this->connection->executeQuery('SELECT 1');
            $schemaManager = $this->connection->createSchemaManager();

            if (!$schemaManager->tablesExist(['user'])) {
                $failures[] = 'bootstrap user table is missing';
            } else {
                $columns = $schemaManager->listTableColumns('user');
                if (!array_key_exists('tenant_slug', $columns)) {
                    $failures[] = 'bootstrap migration Version20260403113000 is missing (tenant_slug column not found)';
                }
            }
        } catch (\Throwable $exception) {
            $failures[] = 'bootstrap database is not queryable: '.$exception->getMessage();
        }

        $tenantDirectory = $this->tenantPaths->getDirectory();
        if (is_dir($tenantDirectory)) {
            if (!is_writable($tenantDirectory)) {
                $failures[] = sprintf('tenant directory is not writable: %s', $tenantDirectory);
            }
        } else {
            $parentDirectory = dirname($tenantDirectory);
            if (!is_dir($parentDirectory) || !is_writable($parentDirectory)) {
                $failures[] = sprintf('tenant directory cannot be created under: %s', $parentDirectory);
            }
        }

        return $failures;
    }
}
