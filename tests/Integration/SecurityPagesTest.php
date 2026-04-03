<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tenancy\TenantDatabaseSwitcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityPagesTest extends WebTestCase
{
    private const TENANT_SLUG = 'acme-demo';

    public function testTenantSubdomainLoginPageIsReachable(): void
    {
        $client = $this->createPreparedClient([
            'HTTP_HOST' => sprintf('%s.localhost', self::TENANT_SLUG),
        ]);
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Déverrouiller le coffre');
    }

    public function testBaseDomainLoginPageIsReachable(): void
    {
        $client = $this->createPreparedClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Déverrouiller le coffre');
    }

    public function testTenantSubdomainLoginPagePrefillsEmailFromQueryString(): void
    {
        $client = $this->createPreparedClient([
            'HTTP_HOST' => sprintf('%s.localhost', self::TENANT_SLUG),
        ]);
        $crawler = $client->request('GET', '/login?email=owner%40example.com');

        self::assertResponseIsSuccessful();
        self::assertSame('owner@example.com', $crawler->filter('#username')->attr('value'));
    }

    public function testAnonymousProjectRouteRedirectsToLogin(): void
    {
        $client = $this->createPreparedClient([
            'HTTP_HOST' => sprintf('%s.localhost', self::TENANT_SLUG),
        ]);
        $client->request('GET', sprintf('/t/%s/projects', self::TENANT_SLUG));

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testTenantSubdomainRootRedirectsAnonymousUsersToLogin(): void
    {
        $client = $this->createPreparedClient([
            'HTTP_HOST' => sprintf('%s.localhost', self::TENANT_SLUG),
        ]);
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testSuccessfulLoginProvisionsTenantDatabase(): void
    {
        $client = $this->createPreparedClient();
        $this->provisionTenantAdmin($client);

        $crawler = $client->request('GET', '/login', [], [], [
            'HTTP_HOST' => sprintf('%s.localhost', self::TENANT_SLUG),
        ]);
        $client->submit($crawler->selectButton('Entrer dans le vault')->form([
            '_username' => 'admin@example.com',
            '_password' => 'StrongPassword123!',
        ]));

        self::assertResponseRedirects(sprintf('http://%s.localhost/', self::TENANT_SLUG));
        self::assertFileExists($this->tenantDatabasePath());
    }

    public function testSuccessfulDirectLoginFromBaseDomainRedirectsToTenantInstance(): void
    {
        $client = $this->createPreparedClient();
        $this->provisionTenantAdmin($client);

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Entrer dans le vault')->form([
            '_username' => 'admin@example.com',
            '_password' => 'StrongPassword123!',
        ]));

        self::assertResponseRedirects(sprintf('http://%s.localhost/', self::TENANT_SLUG));
        self::assertFileExists($this->tenantDatabasePath());
    }

    public function testLegacyTenantLoginPathIsNotExposedAnymore(): void
    {
        $client = $this->createPreparedClient([
            'HTTP_HOST' => sprintf('%s.localhost', self::TENANT_SLUG),
        ]);
        $client->request('GET', sprintf('/t/%s/login', self::TENANT_SLUG));

        self::assertResponseStatusCodeSame(404);
        self::assertFileDoesNotExist($this->tenantDatabasePath());
    }

    private function tenantDatabasePath(): string
    {
        return sprintf('%s/var/tenants/%s.sqlite', static::getContainer()->getParameter('kernel.project_dir'), self::TENANT_SLUG);
    }

    private function provisionTenantAdmin(KernelBrowser $client): void
    {
        $client->request('POST', '/internal/provisioning/tenant-admin', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer test-provisioning-token',
        ], json_encode([
            'contract' => 'tenant-admin-provisioning:v1',
            'child_app_key' => 'vault',
            'child_app_name' => 'Client Secrets Vault',
            'tenant_uuid' => '11111111-2222-7333-8444-555555555555',
            'tenant_slug' => self::TENANT_SLUG,
            'tenant_name' => 'Acme Demo',
            'user_uuid' => 'aaaaaaaa-bbbb-7ccc-8ddd-eeeeeeeeeeee',
            'email' => 'admin@example.com',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'status' => 'active',
            'created_at' => '2026-03-13T20:00:00+00:00',
            'updated_at' => '2026-03-13T20:00:00+00:00',
            'password' => 'StrongPassword123!',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
    }

    /**
     * @param array<string, string> $server
     */
    private function createPreparedClient(array $server = []): KernelBrowser
    {
        self::ensureKernelShutdown();
        $client = static::createClient([], $server);
        $container = static::getContainer();

        /** @var TenantDatabaseSwitcher $switcher */
        $switcher = $container->get(TenantDatabaseSwitcher::class);
        $switcher->resetToBaseDatabase();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        $connection->executeStatement('PRAGMA foreign_keys = OFF');
        $schemaManager = $connection->createSchemaManager();
        foreach ($schemaManager->listTableNames() as $tableName) {
            $connection->executeStatement(sprintf('DROP TABLE IF EXISTS "%s"', $tableName));
        }
        $connection->executeStatement('PRAGMA foreign_keys = ON');

        $tool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());

        $tenantDatabasePath = sprintf('%s/var/tenants/%s.sqlite', $container->getParameter('kernel.project_dir'), self::TENANT_SLUG);
        if (is_file($tenantDatabasePath)) {
            unlink($tenantDatabasePath);
        }

        return $client;
    }
}
