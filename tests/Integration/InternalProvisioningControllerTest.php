<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class InternalProvisioningControllerTest extends WebTestCase
{
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
        self::ensureKernelShutdown();
    }

    public function testEndpointRejectsMissingBearerToken(): void
    {
        $client = static::createClient();
        $client->request('POST', '/internal/provisioning/tenant-admin', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($this->validPayload(), JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(401);
    }

    public function testEndpointRejectsInvalidPayload(): void
    {
        $client = static::createClient();
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
        $client = static::createClient();
        $client->request('POST', '/internal/provisioning/tenant-admin', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer test-provisioning-token',
        ], json_encode($this->validPayload(), JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(201);

        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        $user = $users->findOneByProvisioningIdentity(
            '11111111-2222-7333-8444-555555555555',
            'aaaaaaaa-bbbb-7ccc-8ddd-eeeeeeeeeeee',
        );

        self::assertInstanceOf(User::class, $user);
        self::assertSame('admin@example.com', $user->getEmail());
        self::assertTrue($user->isActive());

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'StrongPassword123!'));
    }

    public function testEndpointAcceptsExtendedChildAppMetadata(): void
    {
        $client = static::createClient();
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
        $client = static::createClient();
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

        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        $user = $users->findOneByProvisioningIdentity(
            '11111111-2222-7333-8444-555555555555',
            'aaaaaaaa-bbbb-7ccc-8ddd-eeeeeeeeeeee',
        );

        self::assertInstanceOf(User::class, $user);
        self::assertSame('admin.updated@example.com', $user->getEmail());
        self::assertSame('Grace Hopper', $user->getDisplayName());
        self::assertFalse($user->isActive());
        self::assertCount(1, $users->findAll());

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
        return array_merge([
            'contract' => 'tenant-admin-provisioning:v1',
            'child_app_key' => 'vault',
            'child_app_name' => 'Client Secrets Vault',
            'tenant_uuid' => '11111111-2222-7333-8444-555555555555',
            'tenant_slug' => 'acme-demo',
            'tenant_name' => 'Acme Demo',
            'user_uuid' => 'aaaaaaaa-bbbb-7ccc-8ddd-eeeeeeeeeeee',
            'email' => 'admin@example.com',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'status' => 'active',
            'created_at' => '2026-03-13T20:00:00+00:00',
            'updated_at' => '2026-03-13T20:00:00+00:00',
            'password' => 'StrongPassword123!',
        ], $overrides);
    }
}
