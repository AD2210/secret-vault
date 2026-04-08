<?php

declare(strict_types=1);

namespace App\Controller;

use App\Audit\AuditLogger;
use App\Entity\AuditLog;
use App\Entity\Project;
use App\Entity\Secret;
use App\Entity\User;
use App\Form\SecretRevealType;
use App\Form\SecretType;
use App\Security\SecretRevealGate;
use App\Security\SecretVoter;
use App\Secrets\SecretPayloadCodec;
use App\Secrets\SecretTypeRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class SecretController extends AbstractController
{
    public function __construct(
        private readonly SecretPayloadCodec $payloadCodec,
        private readonly SecretTypeRegistry $secretTypes,
        private readonly AuditLogger $auditLogger,
        private readonly SecretRevealGate $revealGate,
        private readonly TotpAuthenticatorInterface $totpAuthenticator,
    ) {
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
            $this->auditLogger->logSecretEvent(AuditLog::EVENT_SECRET_CREATED, $secret, $this->getCurrentUser(), $request, [
                'type' => $requestedType,
            ]);

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
            $this->auditLogger->logSecretEvent(AuditLog::EVENT_SECRET_UPDATED, $secret, $this->getCurrentUser(), $request, [
                'type' => $secretType,
            ]);

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

    #[Route('/projects/secret/{id}/reveal', name: 'app_secret_reveal', methods: ['POST'])]
    #[IsGranted(SecretVoter::VIEW, subject: 'secret')]
    public function reveal(Request $request, Secret $secret): Response
    {
        $user = $this->getCurrentUser();
        $projectId = $secret->getProject()?->getIdString();

        if ($this->revealGate->isGranted($request->getSession(), $secret)) {
            return $this->redirectToRoute('app_project_show', ['id' => $projectId]);
        }

        if (!$user->isTotpAuthenticationEnabled()) {
            $this->addFlash('error', 'Activez le TOTP avant de révéler un secret.');

            return $this->redirectToRoute('app_2fa_setup');
        }

        $form = $this->createForm(SecretRevealType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $code = (string) $form->get('code')->getData();
            if ($this->totpAuthenticator->checkCode($user, $code)) {
                $expiresAt = $this->revealGate->grant($request->getSession(), $secret);
                $this->auditLogger->logSecretEvent(AuditLog::EVENT_SECRET_REVEAL_GRANTED, $secret, $user, $request, [
                    'expires_at' => $expiresAt->format(DATE_ATOM),
                ]);
                $this->addFlash('success', sprintf('Secret visible pendant %d secondes.', $this->revealGate->ttlSeconds()));

                return $this->redirectToRoute('app_project_show', ['id' => $projectId]);
            }

            $this->addFlash('error', 'Le code TOTP est invalide.');
        }

        return $this->redirectToRoute('app_project_show', [
            'id' => $projectId,
            'reveal' => $secret->getIdString(),
        ]);
    }

    #[Route('/projects/secret/{id}/copy-log', name: 'app_secret_copy_log', methods: ['POST'])]
    #[IsGranted(SecretVoter::VIEW, subject: 'secret')]
    public function logCopy(Request $request, Secret $secret): Response
    {
        if (!$request->isXmlHttpRequest() || !$this->revealGate->isGranted($request->getSession(), $secret)) {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        $this->auditLogger->logSecretEvent(AuditLog::EVENT_SECRET_COPIED, $secret, $this->getCurrentUser(), $request);

        return new Response(status: Response::HTTP_NO_CONTENT);
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
            ->setPublicSecretEncrypted(null)
            ->setPrivateSecretEncrypted(null);

        $this->payloadCodec->encodeIntoSecret($secret, $payload);
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
