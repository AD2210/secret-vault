<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Entity\ProjectAccessInvitation;
use App\Entity\User;
use App\Form\InvitationRegistrationType;
use App\Form\ProjectType;
use App\Repository\ProjectAccessInvitationRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Security\ProjectVoter;
use App\Security\VaultCipher;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantUserSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/t/{tenantSlug}/projects')]
final class ProjectController extends AbstractController
{
    public function __construct(
        private readonly VaultCipher $cipher,
        private readonly MailerInterface $mailer,
        private readonly TenantContext $tenantContext,
        private readonly TenantUserSynchronizer $tenantUsers,
    ) {
    }

    #[Route('/invitation/{token}', name: 'app_project_invitation_accept', methods: ['GET', 'POST'])]
    public function acceptInvitation(
        string $token,
        Request $request,
        ProjectAccessInvitationRepository $invitations,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): Response {
        $invitation = $invitations->findOneByPlainToken($token);
        if (!$invitation instanceof ProjectAccessInvitation) {
            throw $this->createNotFoundException();
        }

        if ($invitation->isRevoked()) {
            return $this->render('project/invitation_status.html.twig', [
                'invitation' => $invitation,
                'state' => 'revoked',
            ]);
        }

        if ($invitation->isExpired()) {
            return $this->render('project/invitation_status.html.twig', [
                'invitation' => $invitation,
                'state' => 'expired',
            ]);
        }

        if ($invitation->isAccepted()) {
            return $this->render('project/invitation_status.html.twig', [
                'invitation' => $invitation,
                'state' => 'accepted',
            ]);
        }

        $existingUser = $users->findOneByEmailInTenant($invitation->getEmail(), $this->getTenantUuidForInvitation($invitation));
        $currentUser = $this->getUser();

        if ($currentUser instanceof User) {
            if ($currentUser->getEmail() !== $invitation->getEmail()) {
                return $this->render('project/invitation_status.html.twig', [
                    'invitation' => $invitation,
                    'state' => 'wrong_user',
                ]);
            }

            $invitation->setInviteeUser($currentUser);
            $justConfirmed = !$invitation->isRecipientConfirmed();
            $invitation->markRecipientConfirmed();

            if ($invitation->isApproved()) {
                $invitation->getProject()->addMember($currentUser);
                $invitation->markAccepted();
            }

            $em->flush();

            if ($justConfirmed && !$invitation->isApproved()) {
                $this->sendInvitationApprovalRequest($invitation);
            }

            return $this->render('project/invitation_status.html.twig', [
                'invitation' => $invitation,
                'state' => $invitation->isAccepted() ? 'accepted' : 'pending_owner',
            ]);
        }

        if ($existingUser instanceof User) {
            return $this->render('project/invitation_status.html.twig', [
                'invitation' => $invitation,
                'state' => 'login_required',
            ]);
        }

        $user = (new User())
            ->setEmail($invitation->getEmail())
            ->setTenantSlug($this->tenantContext->requireTenantSlug())
            ->setIsActive(true);
        $form = $this->createForm(InvitationRegistrationType::class, $user, [
            'email' => $invitation->getEmail(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $user->setPassword($hasher->hashPassword($user, $plainPassword));

            $em->persist($user);
            $invitation->setInviteeUser($user);
            $invitation->markRecipientConfirmed();

            if ($invitation->isApproved()) {
                $invitation->getProject()->addMember($user);
                $invitation->markAccepted();
            }

            $em->flush();
            $this->tenantUsers->syncTenantUserToBootstrap($user);

            if (!$invitation->isApproved()) {
                $this->sendInvitationApprovalRequest($invitation);
            }

            return $this->render('project/invitation_status.html.twig', [
                'invitation' => $invitation,
                'state' => $invitation->isAccepted() ? 'accepted' : 'pending_owner',
            ]);
        }

        return $this->render('project/invitation_register.html.twig', [
            'invitation' => $invitation,
            'form' => $form,
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('', name: 'app_project_index', methods: ['GET'])]
    public function index(ProjectRepository $projects): Response
    {
        return $this->render('project/index.html.twig', [
            'projects' => $projects->findAccessibleByUser($this->getCurrentUser()),
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project, [
            'plaintext_defaults' => [],
            'tenant_uuid' => $this->getCurrentTenantUuid(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getCurrentUser();
            $project->setCreatedBy($user);
            $project->addMember($user);
            $this->hydrateEncryptedFields($project, $form);
            $this->handleInviteEmail($form, $project, $user, $em);

            $em->persist($project);
            $em->flush();

            $this->addFlash('success', 'Projet enregistré.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getIdString()]);
        }

        return $this->render('project/form.html.twig', [
            'title' => 'Nouveau projet',
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/{id}', name: 'app_project_show', methods: ['GET'])]
    #[IsGranted(ProjectVoter::VIEW, subject: 'project')]
    public function show(Project $project): Response
    {
        return $this->render('project/show.html.twig', [
            'project' => $project,
            'plaintext' => $this->decryptProject($project),
            'secretPlaintexts' => $this->decryptSecrets($project),
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/{id}/edit', name: 'app_project_edit', methods: ['GET', 'POST'])]
    #[IsGranted(ProjectVoter::EDIT, subject: 'project')]
    public function edit(Request $request, Project $project, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProjectType::class, $project, [
            'plaintext_defaults' => $this->decryptProject($project),
            'tenant_uuid' => $this->getCurrentTenantUuid(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project->addMember($project->getCreatedBy());
            $project->addMember($this->getCurrentUser());
            $this->hydrateEncryptedFields($project, $form);
            $this->handleInviteEmail($form, $project, $this->getCurrentUser(), $em);
            $em->flush();

            $this->addFlash('success', 'Projet mis à jour.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getIdString()]);
        }

        return $this->render('project/form.html.twig', [
            'title' => 'Modifier le projet',
            'project' => $project,
            'form' => $form,
            'pendingInvitations' => $project->getAccessInvitations()->toArray(),
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/{id}/delete', name: 'app_project_delete', methods: ['POST'])]
    #[IsGranted(ProjectVoter::EDIT, subject: 'project')]
    public function delete(Request $request, Project $project, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete-project-'.$project->getIdString(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($project);
        $em->flush();

        $this->addFlash('success', 'Projet supprimé.');

        return $this->redirectToRoute('app_project_index');
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/{id}/invitations/{invitation}/approve', name: 'app_project_invitation_approve', methods: ['POST'])]
    public function approveInvitation(
        Request $request,
        Project $project,
        ProjectAccessInvitation $invitation,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);
        $this->assertProjectOwnerOrAdmin($project);
        $this->assertInvitationBelongsToProject($project, $invitation);

        if (!$this->isCsrfTokenValid('approve-invitation-'.$invitation->getIdString(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $invitation->approve();
        if ($invitation->isRecipientConfirmed() && $invitation->getInviteeUser() instanceof User) {
            $project->addMember($invitation->getInviteeUser());
            $invitation->markAccepted();
        }

        $em->flush();

        $this->addFlash('success', 'Invitation validée.');

        return $this->redirectToRoute('app_project_edit', ['id' => $project->getIdString()]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/{id}/invitations/{invitation}/revoke', name: 'app_project_invitation_revoke', methods: ['POST'])]
    public function revokeInvitation(
        Request $request,
        Project $project,
        ProjectAccessInvitation $invitation,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);
        $this->assertProjectOwnerOrAdmin($project);
        $this->assertInvitationBelongsToProject($project, $invitation);

        if (!$this->isCsrfTokenValid('revoke-invitation-'.$invitation->getIdString(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $invitation->revoke();
        $em->flush();

        $this->addFlash('success', 'Invitation révoquée.');

        return $this->redirectToRoute('app_project_edit', ['id' => $project->getIdString()]);
    }

    /**
     * @return array<string, string|null>
     */
    private function decryptProject(Project $project): array
    {
        return [
            'sshPublicKey' => $this->cipher->decrypt($project->getSshPublicKeyEncrypted()),
            'sshPrivateKey' => $this->cipher->decrypt($project->getSshPrivateKeyEncrypted()),
            'serverPassword' => $this->cipher->decrypt($project->getServerPasswordEncrypted()),
            'appSecret' => $this->cipher->decrypt($project->getAppSecretEncrypted()),
            'dbName' => $this->cipher->decrypt($project->getDbNameEncrypted()),
            'dbUser' => $this->cipher->decrypt($project->getDbUserEncrypted()),
            'dbPassword' => $this->cipher->decrypt($project->getDbPasswordEncrypted()),
        ];
    }

    private function hydrateEncryptedFields(Project $project, \Symfony\Component\Form\FormInterface $form): void
    {
        $project->setSshPublicKeyEncrypted($this->cipher->encrypt((string) $form->get('sshPublicKey')->getData()));
        $project->setSshPrivateKeyEncrypted($this->cipher->encrypt((string) $form->get('sshPrivateKey')->getData()));
        $project->setServerPasswordEncrypted($this->cipher->encrypt((string) $form->get('serverPassword')->getData()));
        $project->setAppSecretEncrypted($this->cipher->encrypt((string) $form->get('appSecret')->getData()));
        $project->setDbNameEncrypted($this->cipher->encrypt((string) $form->get('dbName')->getData()));
        $project->setDbUserEncrypted($this->cipher->encrypt((string) $form->get('dbUser')->getData()));
        $project->setDbPasswordEncrypted($this->cipher->encrypt((string) $form->get('dbPassword')->getData()));
    }

    /**
     * @return array<string, array{publicSecret: string|null, privateSecret: string|null}>
     */
    private function decryptSecrets(Project $project): array
    {
        $plaintexts = [];
        foreach ($project->getSecrets() as $secret) {
            $plaintexts[$secret->getIdString()] = [
                'publicSecret' => $this->cipher->decrypt($secret->getPublicSecretEncrypted()),
                'privateSecret' => $this->cipher->decrypt($secret->getPrivateSecretEncrypted()),
            ];
        }

        return $plaintexts;
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function handleInviteEmail(FormInterface $form, Project $project, User $actor, EntityManagerInterface $em): void
    {
        $inviteEmail = mb_strtolower(trim((string) $form->get('inviteEmail')->getData()));
        if ('' === $inviteEmail) {
            return;
        }

        foreach ($project->getMembers() as $member) {
            if ($member->getEmail() === $inviteEmail) {
                $this->addFlash('error', 'Cet utilisateur a déjà accès au projet.');

                return;
            }
        }

        /** @var ProjectAccessInvitationRepository $invitations */
        $invitations = $em->getRepository(ProjectAccessInvitation::class);
        if ($invitations->hasPendingInvitation($project, $inviteEmail)) {
            $this->addFlash('error', 'Une invitation en cours existe déjà pour cet email.');

            return;
        }

        $plainToken = bin2hex(random_bytes(32));
        $invitation = new ProjectAccessInvitation(
            $project,
            $actor,
            $inviteEmail,
            hash('sha256', $plainToken),
            new \DateTimeImmutable('+7 days'),
        );

        /** @var UserRepository $users */
        $users = $em->getRepository(User::class);
        $existingUser = $users->findOneByEmailInTenant($inviteEmail, $this->getTenantUuidForProject($project));
        if ($existingUser instanceof User) {
            $invitation->setInviteeUser($existingUser);
        }

        $project->addAccessInvitation($invitation);
        $em->persist($invitation);
        $this->sendInvitationEmail($invitation, $plainToken);
        $this->addFlash('success', 'Invitation envoyée.');
    }

    private function sendInvitationEmail(ProjectAccessInvitation $invitation, string $plainToken): void
    {
        $this->mailer->send((new TemplatedEmail())
            ->to(new Address($invitation->getEmail()))
            ->subject(sprintf('Invitation au projet "%s"', $invitation->getProject()->getName()))
            ->htmlTemplate('project/emails/invitation.html.twig')
            ->context([
                'invitation' => $invitation,
                'acceptUrl' => $this->generateUrl('app_project_invitation_accept', [
                    'token' => $plainToken,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ]));
    }

    private function sendInvitationApprovalRequest(ProjectAccessInvitation $invitation): void
    {
        $this->mailer->send((new TemplatedEmail())
            ->to(new Address($invitation->getInvitedBy()->getEmail(), $invitation->getInvitedBy()->getDisplayName()))
            ->subject(sprintf('Validation requise pour "%s"', $invitation->getProject()->getName()))
            ->htmlTemplate('project/emails/invitation_owner_approval.html.twig')
            ->context([
                'invitation' => $invitation,
                'projectEditUrl' => $this->generateUrl('app_project_edit', [
                    'id' => $invitation->getProject()->getIdString(),
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ]));
    }

    private function assertProjectOwnerOrAdmin(Project $project): void
    {
        $user = $this->getCurrentUser();
        if ($user->isAdmin() || $project->getCreatedBy()->getId()->equals($user->getId())) {
            return;
        }

        throw $this->createAccessDeniedException();
    }

    private function assertInvitationBelongsToProject(Project $project, ProjectAccessInvitation $invitation): void
    {
        if ($invitation->getProject()->getId()->equals($project->getId())) {
            return;
        }

        throw $this->createNotFoundException();
    }

    private function getCurrentTenantUuid(): ?string
    {
        return $this->getCurrentUser()->getExternalTenantUuid();
    }

    private function getTenantUuidForProject(Project $project): ?string
    {
        return $project->getCreatedBy()->getExternalTenantUuid();
    }

    private function getTenantUuidForInvitation(ProjectAccessInvitation $invitation): ?string
    {
        return $this->getTenantUuidForProject($invitation->getProject());
    }
}
