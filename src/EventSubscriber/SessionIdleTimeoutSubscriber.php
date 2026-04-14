<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class SessionIdleTimeoutSubscriber implements EventSubscriberInterface
{
    private const string LAST_ACTIVITY_SESSION_KEY = 'vault.last_activity_at';

    /**
     * @param list<string> $allowedRoutes
     */
    public function __construct(
        private readonly Security $security,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly int $timeoutSeconds,
        private readonly array $allowedRoutes,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = trim((string) $request->attributes->get('_route', ''));
        if ('' !== $route && in_array($route, $this->allowedRoutes, true)) {
            return;
        }

        $session = $request->getSession();
        if (!$session->isStarted()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $now = time();
        $lastActivity = $session->get(self::LAST_ACTIVITY_SESSION_KEY);
        if (is_int($lastActivity) && ($now - $lastActivity) > $this->timeoutSeconds) {
            $this->tokenStorage->setToken(null);
            $session->invalidate();

            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login', [
                'timed_out' => 1,
            ])));

            return;
        }

        $session->set(self::LAST_ACTIVITY_SESSION_KEY, $now);
    }
}
