<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Secret;
use App\Form\SecretType;
use App\Security\ProjectVoter;
use App\Security\SecretVoter;
use App\Security\VaultCipher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/t/{tenantSlug}/projects')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class SecretController extends AbstractController
{
    public function __construct(private readonly VaultCipher $cipher)
    {
    }

    #[Route('/{id}/secrets/new', name: 'app_secret_new', methods: ['GET', 'POST'])]
    #[IsGranted(ProjectVoter::EDIT, subject: 'project')]
    public function new(Request $request, Project $project, EntityManagerInterface $em): Response
    {
        $secret = new Secret();
        $secret->setProject($project);
        $form = $this->createForm(SecretType::class, $secret, [
            'plaintext_defaults' => [],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->hydrateEncryptedFields($secret, $form);
            $project->addSecret($secret);
            $em->persist($secret);
            $em->flush();

            $this->addFlash('success', 'Secret ajouté.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getIdString()]);
        }

        return $this->render('secret/form.html.twig', [
            'title' => 'Nouveau secret',
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/secret/{id}/edit', name: 'app_secret_edit', methods: ['GET', 'POST'])]
    #[IsGranted(SecretVoter::EDIT, subject: 'secret')]
    public function edit(Request $request, Secret $secret, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SecretType::class, $secret, [
            'plaintext_defaults' => [
                'publicSecret' => $this->cipher->decrypt($secret->getPublicSecretEncrypted()),
                'privateSecret' => $this->cipher->decrypt($secret->getPrivateSecretEncrypted()),
            ],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->hydrateEncryptedFields($secret, $form);
            $em->flush();

            $this->addFlash('success', 'Secret mis à jour.');

            return $this->redirectToRoute('app_project_show', ['id' => $secret->getProject()?->getIdString()]);
        }

        return $this->render('secret/form.html.twig', [
            'title' => 'Modifier le secret',
            'project' => $secret->getProject(),
            'form' => $form,
        ]);
    }

    private function hydrateEncryptedFields(Secret $secret, FormInterface $form): void
    {
        $secret->setPublicSecretEncrypted($this->cipher->encrypt((string) $form->get('publicSecret')->getData()));
        $secret->setPrivateSecretEncrypted($this->cipher->encrypt((string) $form->get('privateSecret')->getData()));
    }
}
