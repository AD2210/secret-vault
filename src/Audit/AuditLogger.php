<?php

declare(strict_types=1);

namespace App\Audit;

use App\Entity\AuditLog;
use App\Entity\Secret;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class AuditLogger
{
    public function __construct(
        private EntityManagerInterface $em,
        private AuditLogRepository $auditLogs,
        private SecurityAlertNotifier $alerts,
    ) {
    }

    /**
     * @param array<string, scalar|array<array-key, scalar|null>|null> $context
     */
    public function logSecretEvent(string $eventType, Secret $secret, ?User $actor, ?Request $request = null, array $context = []): void
    {
        $ipAddress = $request?->getClientIp();
        $userAgent = $request?->headers->get('User-Agent');
        $isNewFingerprint = $actor instanceof User
            && in_array($eventType, [AuditLog::EVENT_SECRET_REVEAL_GRANTED, AuditLog::EVENT_SECRET_COPIED], true)
            && !$this->auditLogs->hasSeenFingerprint($actor, $ipAddress, $userAgent, new \DateTimeImmutable('-30 days'));

        $log = (new AuditLog($eventType))
            ->setActor($actor)
            ->setSubjectType('secret')
            ->setSubjectId($secret->getIdString())
            ->setIpAddress($ipAddress)
            ->setUserAgent($userAgent)
            ->setContext($context);

        $this->em->persist($log);
        $this->em->flush();

        if ($isNewFingerprint && $actor instanceof User) {
            $this->alerts->notifyUnusualSecretAccess($actor, $secret, $eventType, $ipAddress, $userAgent);
        }
    }
}
