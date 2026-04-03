<?php

declare(strict_types=1);

namespace App\Controller;

use App\Tenancy\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TenantEntryController extends AbstractController
{
    #[Route('/', name: 'app_tenant_entry', methods: ['GET'])]
    public function index(TenantContext $tenantContext): Response
    {
        $tenantSlug = $tenantContext->getTenantSlug();
        if (null === $tenantSlug) {
            throw $this->createNotFoundException();
        }

        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->redirectToRoute('app_login');
    }

    #[Route('/login', name: 'app_tenant_login_entry', methods: ['GET'])]
    public function login(TenantContext $tenantContext): Response
    {
        if (null === $tenantContext->getTenantSlug()) {
            throw $this->createNotFoundException();
        }

        return $this->redirectToRoute('app_login');
    }
}
