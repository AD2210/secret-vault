<?php

declare(strict_types=1);

namespace App\Audit;

use App\Entity\Secret;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final readonly class SecurityAlertNotifier
{
    /**
     * @param list<string> $recipients
     */
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%app.security.alert_recipients%')]
        private array $recipients,
    ) {
    }

    public function notifyUnusualSecretAccess(User $actor, Secret $secret, string $eventType, ?string $ipAddress, ?string $userAgent): void
    {
        if ([] === $this->recipients) {
            return;
        }

        $email = (new Email())
            ->subject(sprintf('Alerte sécurité: accès inhabituel au secret "%s"', $secret->getName()))
            ->text(implode("\n", [
                sprintf('Utilisateur: %s <%s>', $actor->getDisplayName(), $actor->getEmail()),
                sprintf('Événement: %s', $eventType),
                sprintf('Projet: %s', $secret->getProject()?->getName() ?? 'n/a'),
                sprintf('Secret: %s', $secret->getName()),
                sprintf('IP: %s', $ipAddress ?? 'inconnue'),
                sprintf('User-Agent: %s', $userAgent ?? 'inconnu'),
                sprintf('Date: %s', (new \DateTimeImmutable())->format(DATE_ATOM)),
            ]));

        foreach ($this->recipients as $recipient) {
            $normalized = trim($recipient);
            if ('' === $normalized) {
                continue;
            }

            $email->addTo(new Address($normalized));
        }

        if ([] === $email->getTo()) {
            return;
        }

        $this->mailer->send($email);
    }
}
