<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        AuthenticationUtils $authenticationUtils,
        TenantContext $tenantContext,
        TenantUrlGenerator $tenantUrls,
    ): Response
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            if (null === $tenantContext->getTenantSlug()) {
                $dashboardUrl = $tenantUrls->generateTenantDashboardUrlForUser($user);
                if (null !== $dashboardUrl) {
                    return $this->redirect($dashboardUrl);
                }
            }

            return $this->redirectToRoute('app_dashboard');
        }

        $lastUsername = $authenticationUtils->getLastUsername();
        if ('' === $lastUsername) {
            $prefilledEmail = trim((string) $request->query->get('email', ''));
            if ('' !== $prefilledEmail) {
                $lastUsername = $prefilledEmail;
            }
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('This method is intercepted by the firewall logout handler.');
    }
}
