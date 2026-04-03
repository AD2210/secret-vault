<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthEndpointsTest extends WebTestCase
{
    public function testHealthEndpointReturnsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/healthz');

        self::assertResponseIsSuccessful();
        self::assertSame('OK', $client->getResponse()->getContent());
    }

    public function testReadyEndpointReturnsReady(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $this->prepareSchema();
        $client->request('GET', '/ready');

        self::assertResponseIsSuccessful();
        self::assertSame('READY', $client->getResponse()->getContent());
    }

    private function prepareSchema(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        $connection->executeStatement('PRAGMA foreign_keys = OFF');
        $schemaManager = $connection->createSchemaManager();
        foreach ($schemaManager->listTableNames() as $tableName) {
            $connection->executeStatement(sprintf('DROP TABLE IF EXISTS "%s"', $tableName));
        }
        $connection->executeStatement('PRAGMA foreign_keys = ON');

        $tool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
    }
}
