<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Secret;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final readonly class SecretRevealGate
{
    private const string SESSION_KEY = 'vault.secret_reveal_grants';

    public function __construct(
        #[Autowire('%app.security.secret_reveal_ttl%')]
        private int $ttlSeconds,
    ) {
    }

    public function isGranted(SessionInterface $session, Secret $secret): bool
    {
        return null !== $this->expiresAt($session, $secret);
    }

    public function ttlSeconds(): int
    {
        return $this->ttlSeconds;
    }

    public function grant(SessionInterface $session, Secret $secret): \DateTimeImmutable
    {
        $this->cleanup($session);

        $expiresAt = new \DateTimeImmutable(sprintf('+%d seconds', $this->ttlSeconds));
        $grants = $session->get(self::SESSION_KEY, []);
        $grants[$secret->getIdString()] = $expiresAt->getTimestamp();
        $session->set(self::SESSION_KEY, $grants);

        return $expiresAt;
    }

    public function expiresAt(SessionInterface $session, Secret $secret): ?\DateTimeImmutable
    {
        $this->cleanup($session);

        $grants = $session->get(self::SESSION_KEY, []);
        $timestamp = $grants[$secret->getIdString()] ?? null;
        if (!is_int($timestamp)) {
            return null;
        }

        return (new \DateTimeImmutable())->setTimestamp($timestamp);
    }

    private function cleanup(SessionInterface $session): void
    {
        $grants = $session->get(self::SESSION_KEY, []);
        if (!is_array($grants) || [] === $grants) {
            $session->remove(self::SESSION_KEY);

            return;
        }

        $now = time();
        $filtered = array_filter($grants, static fn (mixed $timestamp): bool => is_int($timestamp) && $timestamp > $now);
        if ([] === $filtered) {
            $session->remove(self::SESSION_KEY);

            return;
        }

        $session->set(self::SESSION_KEY, $filtered);
    }
}
