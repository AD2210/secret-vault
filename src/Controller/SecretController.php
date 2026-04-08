<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Secret;
use App\Entity\User;
use App\Form\SecretType;
use App\Security\SecretVoter;
use App\Secrets\SecretPayloadCodec;
use App\Secrets\SecretTypeRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class SecretController extends AbstractController
{
    public function __construct(
        private readonly SecretPayloadCodec $payloadCodec,
        private readonly SecretTypeRegistry $secretTypes,
    )
    {
    }

    #[Route('/projects/{id}/secrets/new', name: 'app_secret_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Project $project, EntityManagerInterface $em): Response
    {
        $this->denyUnlessCanCreateSecret($project);

        $requestedType = trim((string) $request->query->get('type', ''));
        if (!$this->secretTypes->supports($requestedType)) {
            return $this->render('secret/type_picker.html.twig', [
                'project' => $project,
                'secretTypes' => $this->secretTypes->all(),
            ]);
        }

        $secret = new Secret();
        $secret->setProject($project);
        $secret->setType($requestedType);
        $secret->setCreatedBy($this->getCurrentUser());
        $form = $this->createForm(SecretType::class, $secret, [
            'plaintext_defaults' => $this->payloadCodec->decode($secret),
            'secret_type' => $requestedType,
            'locked_type' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->hydrateEncryptedFields($secret, $form, $requestedType);
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
            'secretDefinition' => $this->secretTypes->get($requestedType),
            'secretType' => $requestedType,
        ]);
    }

    #[Route('/projects/secret/{id}/edit', name: 'app_secret_edit', methods: ['GET', 'POST'])]
    #[IsGranted(SecretVoter::EDIT, subject: 'secret')]
    public function edit(Request $request, Secret $secret, EntityManagerInterface $em): Response
    {
        $secretType = $this->secretTypes->supports($secret->getType()) ? $secret->getType() : Secret::TYPE_SECRET;
        $form = $this->createForm(SecretType::class, $secret, [
            'plaintext_defaults' => $this->payloadCodec->decode($secret),
            'secret_type' => $secretType,
            'locked_type' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->hydrateEncryptedFields($secret, $form, $secretType);
            $em->flush();

            $this->addFlash('success', 'Secret mis à jour.');

            return $this->redirectToRoute('app_project_show', ['id' => $secret->getProject()?->getIdString()]);
        }

        return $this->render('secret/form.html.twig', [
            'title' => 'Modifier le secret',
            'project' => $secret->getProject(),
            'form' => $form,
            'secretDefinition' => $this->secretTypes->get($secretType),
            'secretType' => $secretType,
        ]);
    }

    private function hydrateEncryptedFields(Secret $secret, FormInterface $form, string $secretType): void
    {
        $payload = [];
        foreach ($this->secretTypes->get($secretType)['fields'] as $field) {
            $value = $form->get($field['key'])->getData();
            if (is_string($value)) {
                $value = trim($value);
            }

            if (null === $value || '' === $value) {
                continue;
            }

            $payload[$field['key']] = $value;
        }

        $secret
            ->setType($secretType)
            ->setPayloadEncrypted($this->payloadCodec->encode($payload))
            ->setPublicSecretEncrypted(null)
            ->setPrivateSecretEncrypted(null);
    }

    private function denyUnlessCanCreateSecret(Project $project): void
    {
        $user = $this->getCurrentUser();
        if ($project->isAccessibleBy($user) && $user->canCreateSecrets()) {
            return;
        }

        throw $this->createAccessDeniedException();
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
