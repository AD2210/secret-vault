<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tenancy\TenantDatabaseSwitcher;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class InternalProvisioningControllerTest extends WebTestCase
{
    public function testEndpointRejectsMissingBearerToken(): void
    {
        $client = $this->createPreparedClient();
        $client->request('POST', '/internal/provisioning/tenant-admin', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($this->validPayload(), JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(401);
    }

    public function testEndpointRejectsInvalidPayload(): void
    {
        $client = $this->createPreparedClient();
        $client->request('POST', '/internal/provisioning/tenant-admin', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer test-provisioning-token',
        ], json_encode(['contract' => 'tenant-admin-provisioning:v1'], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('invalid', $payload['status']);
        self::assertNotEmpty($payload['errors']);
    }

    public function testEndpointCreatesProvisionedUser(): void
    {
        $client = $this->createPreparedClient();
        $client->request('POST', '/internal/provisioning/tenant-admin', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer test-provisioning-token',
        ], json_encode($this->validPayload(), JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(201);

        $payload = $this->validPayload();
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        $user = $users->findOneByEmailAndTenantSlug($payload['email'], $payload['tenant_slug']);

        self::assertInstanceOf(User::class, $user);
        self::assertSame($payload['email'], $user->getEmail());
        self::assertTrue($user->isActive());
        self::assertFalse(is_file($this->tenantDatabasePath($payload['tenant_slug'])));

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'StrongPassword123!'));
    }

    public function testEndpointAcceptsExtendedChildAppMetadata(): void
    {
        $client = $this->createPreparedClient();
        $client->request('POST', '/internal/provisioning/tenant-admin', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer test-provisioning-token',
        ], json_encode($this->validPayload([
            'child_app_key' => 'vault',
            'child_app_name' => 'Client Secrets Vault',
            'tenant_slug' => 'acme-demo',
            'tenant_name' => 'Acme Demo',
        ]), JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(201);
    }

    public function testEndpointIsIdempotentAndUpdatesExistingUser(): void
    {
        $client = $this->createPreparedClient();
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer test-provisioning-token',
        ];

        $client->request('POST', '/internal/provisioning/tenant-admin', [], [], $headers, json_encode($this->validPayload(), JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        $updatedPayload = $this->validPayload([
            'email' => 'admin.updated@example.com',
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'status' => 'inactive',
            'password' => 'AnotherStrongPassword123!',
            'updated_at' => '2026-03-13T20:05:00+00:00',
        ]);
        $client->request('POST', '/internal/provisioning/tenant-admin', [], [], $headers, json_encode($updatedPayload, JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(200);

        $updatedEmail = (string) $updatedPayload['email'];
        $tenantSlug = (string) $updatedPayload['tenant_slug'];
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        $user = $users->findOneByEmailAndTenantSlug($updatedEmail, $tenantSlug);

        self::assertInstanceOf(User::class, $user);
        self::assertSame($updatedEmail, $user->getEmail());
        self::assertSame('Grace Hopper', $user->getDisplayName());
        self::assertFalse($user->isActive());
        self::assertCount(1, $users->findBy(['tenantSlug' => $tenantSlug]));

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'AnotherStrongPassword123!'));
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        $seed = $this->payloadSeed();

        return array_merge([
            'contract' => 'tenant-admin-provisioning:v1',
            'child_app_key' => 'vault',
            'child_app_name' => 'Client Secrets Vault',
            'tenant_uuid' => $this->uuidFor('tenant-'.$seed),
            'tenant_slug' => 'tenant-'.$seed,
            'tenant_name' => 'Acme Demo',
            'user_uuid' => $this->uuidFor('user-'.$seed),
            'email' => sprintf('%s@example.com', $seed),
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'status' => 'active',
            'created_at' => '2026-03-13T20:00:00+00:00',
            'updated_at' => '2026-03-13T20:00:00+00:00',
            'password' => 'StrongPassword123!',
        ], $overrides);
    }

    private function tenantDatabasePath(string $tenantSlug): string
    {
        return sprintf('%s/var/tenants/%s.sqlite', static::getContainer()->getParameter('kernel.project_dir'), $tenantSlug);
    }

    private function payloadSeed(): string
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $this->name()));

        return trim($normalized, '-');
    }

    private function uuidFor(string $seed): string
    {
        $hex = md5($seed);

        return sprintf(
            '%s-%s-4%s-a%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 13, 3),
            substr($hex, 17, 3),
            substr($hex, 20, 12),
        );
    }

    private function createPreparedClient(): KernelBrowser
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $container = static::getContainer();

        /** @var TenantDatabaseSwitcher $switcher */
        $switcher = $container->get(TenantDatabaseSwitcher::class);
        $switcher->resetToBaseDatabase();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        $schemaManager = $connection->createSchemaManager();
        if ([] === $schemaManager->listTableNames()) {
            $tool = new SchemaTool($em);
            $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        }

        return $client;
    }
}
