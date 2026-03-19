<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InternalProvisioningController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(string:CHILD_APP_PROVISIONING_TOKEN)%')]
        private readonly string $expectedToken,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/internal/provisioning/tenant-admin', name: 'app_internal_provision_tenant_admin', methods: ['POST'])]
    public function tenantAdmin(Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);
        $tenantUuid = is_string($payload['tenant_uuid'] ?? null) ? $payload['tenant_uuid'] : null;
        $userUuid = is_string($payload['user_uuid'] ?? null) ? $payload['user_uuid'] : null;
        $contract = is_string($payload['contract'] ?? null) ? $payload['contract'] : 'tenant-admin-provisioning:v1';
        $childAppKey = is_string($payload['child_app_key'] ?? null) ? $payload['child_app_key'] : null;

        if (!$this->isAuthorized($request)) {
            return $this->logAndRespond(401, 'tenant.admin.provisioning.unauthorized', [
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], $tenantUuid, $userUuid, $contract, $childAppKey);
        }

        $errors = $this->validatePayload($payload);
        if ([] !== $errors) {
            return $this->logAndRespond(422, 'tenant.admin.provisioning.invalid_payload', [
                'status' => 'invalid',
                'errors' => $errors,
            ], $tenantUuid, $userUuid, $contract, $childAppKey);
        }

        /** @var string $tenantUuid */
        /** @var string $userUuid */
        /** @var string $email */
        /** @var string $firstName */
        /** @var string $lastName */
        /** @var string $status */
        /** @var string $password */
        $email = mb_strtolower(trim((string) $payload['email']));
        $firstName = trim((string) $payload['first_name']);
        $lastName = trim((string) $payload['last_name']);
        $status = (string) $payload['status'];
        $password = (string) $payload['password'];

        $user = $this->users->findOneByProvisioningIdentity($tenantUuid, $userUuid);
        $emailOwner = $this->users->findOneBy(['email' => $email]);
        if ($emailOwner instanceof User && $emailOwner !== $user) {
            return $this->logAndRespond(409, 'tenant.admin.provisioning.email_conflict', [
                'status' => 'conflict',
                'message' => 'Another account already uses this email.',
            ], $tenantUuid, $userUuid, $contract, $childAppKey);
        }

        $statusCode = 200;
        if (!$user instanceof User) {
            $user = new User($email, $firstName, $lastName);
            $user->setExternalTenantUuid($tenantUuid);
            $user->setExternalUserUuid($userUuid);
            $user->setRoles([]);
            $this->em->persist($user);
            $statusCode = 201;
        }

        $user
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setIsActive('active' === $status)
            ->setPassword($this->hasher->hashPassword($user, $password));

        $this->em->flush();

        return $this->logAndRespond($statusCode, 'tenant.admin.provisioning.succeeded', [
            'status' => 201 === $statusCode ? 'created' : 'updated',
            'contract' => $contract,
            'child_app_key' => $childAppKey,
            'tenant_uuid' => $tenantUuid,
            'user_uuid' => $userUuid,
            'email' => $email,
            'user_id' => $user->getIdString(),
        ], $tenantUuid, $userUuid, $contract, $childAppKey);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(Request $request): array
    {
        $content = trim((string) $request->getContent());
        if ('' === $content) {
            return [];
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (\JsonException) {
            return [];
        }
    }

    private function isAuthorized(Request $request): bool
    {
        $header = trim((string) $request->headers->get('Authorization', ''));
        if (!str_starts_with($header, 'Bearer ')) {
            return false;
        }

        $expectedToken = trim($this->expectedToken);
        if ('' === $expectedToken) {
            return false;
        }

        return hash_equals($expectedToken, trim(substr($header, 7)));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function validatePayload(array $payload): array
    {
        $constraints = new Assert\Collection(
            fields: [
                'contract' => [
                    new Assert\NotBlank(),
                    new Assert\EqualTo('tenant-admin-provisioning:v1'),
                ],
                'child_app_key' => new Assert\Optional([
                    new Assert\NotBlank(),
                    new Assert\Length(max: 64),
                    new Assert\Regex('/^[a-z0-9-]+$/'),
                ]),
                'child_app_name' => new Assert\Optional([
                    new Assert\NotBlank(),
                    new Assert\Length(max: 160),
                ]),
                'tenant_uuid' => [
                    new Assert\NotBlank(),
                    new Assert\Uuid(),
                ],
                'tenant_slug' => new Assert\Optional([
                    new Assert\NotBlank(),
                    new Assert\Length(max: 80),
                ]),
                'tenant_name' => new Assert\Optional([
                    new Assert\NotBlank(),
                    new Assert\Length(max: 160),
                ]),
                'user_uuid' => [
                    new Assert\NotBlank(),
                    new Assert\Uuid(),
                ],
                'email' => [
                    new Assert\NotBlank(),
                    new Assert\Email(),
                ],
                'first_name' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 100),
                ],
                'last_name' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 100),
                ],
                'status' => [
                    new Assert\NotBlank(),
                    new Assert\Choice(choices: ['active', 'inactive']),
                ],
                'created_at' => [
                    new Assert\NotBlank(),
                    new Assert\DateTime(format: \DateTimeInterface::ATOM),
                ],
                'updated_at' => [
                    new Assert\NotBlank(),
                    new Assert\DateTime(format: \DateTimeInterface::ATOM),
                ],
                'password' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 12),
                ],
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        );

        $violations = $this->validator->validate($payload, $constraints);
        $errors = [];
        foreach ($violations as $violation) {
            $field = trim((string) $violation->getPropertyPath(), '[]');
            $errors[] = sprintf('%s: %s', $field, $violation->getMessage());
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function logAndRespond(
        int $statusCode,
        string $message,
        array $response,
        ?string $tenantUuid,
        ?string $userUuid,
        string $contract,
        ?string $childAppKey,
    ): JsonResponse {
        $context = [
            'tenant_uuid' => $tenantUuid,
            'user_uuid' => $userUuid,
            'contract' => $contract,
            'child_app_key' => $childAppKey,
            'status_code' => $statusCode,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        if ($statusCode >= 500) {
            $this->logger->error($message, $context);
        } elseif ($statusCode >= 400) {
            $this->logger->warning($message, $context);
        } else {
            $this->logger->info($message, $context);
        }

        return $this->json($response, $statusCode);
    }
}
