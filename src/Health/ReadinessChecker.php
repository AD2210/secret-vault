<?php

declare(strict_types=1);

namespace App\Health;

use Doctrine\DBAL\Connection;

final readonly class ReadinessChecker
{
    public function __construct(
        private Connection $connection,
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
                $failures[] = 'user table is missing';
            }
        } catch (\Throwable $exception) {
            $failures[] = 'database is not queryable: '.$exception->getMessage();
        }

        return $failures;
    }
}
