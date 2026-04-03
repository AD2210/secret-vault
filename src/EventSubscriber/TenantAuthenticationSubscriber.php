<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Tenancy\TenantUserSynchronizer;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final readonly class TenantAuthenticationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TenantUserSynchronizer $tenantUsers,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            TwoFactorAuthenticationEvents::COMPLETE => 'onTwoFactorAuthenticationComplete',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if ($event->getAuthenticatedToken() instanceof TwoFactorTokenInterface) {
            return;
        }

        $user = $event->getUser();
        if ($user instanceof User) {
            $this->tenantUsers->syncBootstrapUserToTenant($user, true);
        }
    }

    public function onTwoFactorAuthenticationComplete(TwoFactorAuthenticationEvent $event): void
    {
        $user = $event->getToken()->getUser();
        if ($user instanceof User) {
            $this->tenantUsers->syncBootstrapUserToTenant($user, true);
        }
    }
}
