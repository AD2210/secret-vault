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

final class ForceTotpEnrollmentSubscriber implements EventSubscriberInterface
{
    /**
     * @param list<string> $allowedRoutes
     */
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly array $allowedRoutes,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route', '');
        if ('' === $route || in_array($route, $this->allowedRoutes, true)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $user->isTotpAuthenticationEnabled()) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_2fa_setup')));
    }
}
