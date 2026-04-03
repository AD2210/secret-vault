<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Tenancy\TenantUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final readonly class TenantAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private TenantUrlGenerator $tenantUrls,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $dashboardUrl = $this->tenantUrls->generateTenantDashboardUrlForUser($user);
        if (null === $dashboardUrl) {
            return null;
        }

        return new RedirectResponse($dashboardUrl);
    }
}
