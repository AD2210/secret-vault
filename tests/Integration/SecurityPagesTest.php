<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tenancy\TenantDatabaseSwitcher;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityPagesTest extends WebTestCase
{
    private const TENANT_SLUG = 'acme-demo';

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        static::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $classes = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($classes);
        $tool->createSchema($classes);

        $tenantDatabasePath = sprintf('%s/var/tenants/%s.sqlite', $container->getParameter('kernel.project_dir'), self::TENANT_SLUG);
        if (is_file($tenantDatabasePath)) {
            unlink($tenantDatabasePath);
        }

        self::ensureKernelShutdown();
    }

    public function testLoginPageIsReachable(): void
    {
        $client = static::createClient();
        $client->request('GET', sprintf('/t/%s/login', self::TENANT_SLUG));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Déverrouiller le coffre');
    }

    public function testLoginPagePrefillsEmailFromQueryString(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', sprintf('/t/%s/login?email=owner%%40example.com', self::TENANT_SLUG));

        self::assertResponseIsSuccessful();
        self::assertSame('owner@example.com', $crawler->filter('#username')->attr('value'));
    }

    public function testDashboardRedirectsAnonymousUsersToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', sprintf('/t/%s', self::TENANT_SLUG));

        self::assertResponseRedirects(sprintf('/t/%s/login', self::TENANT_SLUG));
    }

    public function testTenantSubdomainRootRedirectsAnonymousUsersToLogin(): void
    {
        $client = static::createClient([], [
            'HTTP_HOST' => sprintf('%s.localhost', self::TENANT_SLUG),
        ]);
        $client->request('GET', '/');

        self::assertResponseRedirects(sprintf('/t/%s/login', self::TENANT_SLUG));
    }

    public function testTenantSubdomainLoginAliasRedirectsToTenantLogin(): void
    {
        $client = static::createClient([], [
            'HTTP_HOST' => sprintf('%s.localhost', self::TENANT_SLUG),
        ]);
        $client->request('GET', '/login');

        self::assertResponseRedirects(sprintf('/t/%s/login', self::TENANT_SLUG));
    }

    public function testSuccessfulLoginProvisionsTenantDatabase(): void
    {
        $client = static::createClient();
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

        $crawler = $client->request('GET', sprintf('/t/%s/login', self::TENANT_SLUG));
        $client->submit($crawler->selectButton('Entrer dans le vault')->form([
            '_username' => 'admin@example.com',
            '_password' => 'StrongPassword123!',
        ]));

        self::assertResponseRedirects(sprintf('/t/%s', self::TENANT_SLUG));
        self::assertFileExists($this->tenantDatabasePath());

        /** @var TenantDatabaseSwitcher $switcher */
        $switcher = static::getContainer()->get(TenantDatabaseSwitcher::class);
        $switcher->switchToTenant(self::TENANT_SLUG);

        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        $user = $users->findOneBy(['email' => 'admin@example.com']);
        self::assertInstanceOf(User::class, $user);
    }

    private function tenantDatabasePath(): string
    {
        return sprintf('%s/var/tenants/%s.sqlite', static::getContainer()->getParameter('kernel.project_dir'), self::TENANT_SLUG);
    }
}
