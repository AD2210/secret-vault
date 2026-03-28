<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\SecretRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/t/{tenantSlug}')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'app_dashboard', methods: ['GET'])]
    public function __invoke(ProjectRepository $projects, SecretRepository $secrets): Response
    {
        $user = $this->getCurrentUser();

        return $this->render('dashboard/index.html.twig', [
            'projects' => $projects->findAccessibleByUser($user),
            'projectCount' => $projects->countAccessibleByUser($user),
            'secretCount' => $secrets->countAccessibleByUser($user),
            'currentUser' => $user,
        ]);
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
