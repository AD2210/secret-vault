<?php

declare(strict_types=1);

namespace App\Tenancy;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

final readonly class TenantDatabaseManager
{
    public function __construct(
        private TenantDatabaseSwitcher $switcher,
        private TenantDatabasePathResolver $pathResolver,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function ensureTenantDatabase(string $tenantSlug): string
    {
        $path = $this->switcher->switchToTenant($tenantSlug);
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        if (!is_file($path)) {
            touch($path);
        }

        $tool = new SchemaTool($this->entityManager);
        $classes = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool->updateSchema($classes, true);

        return $path;
    }
}
