<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Entity\ProjectAccessInvitation;
use App\Entity\User;
use App\Form\InvitationRegistrationType;
use App\Form\ProjectMembersType;
use App\Form\ProjectType;
use App\Form\SecretRevealType;
use App\Repository\ProjectAccessInvitationRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Security\ProjectVoter;
use App\Security\SecretRevealGate;
use App\Secrets\SecretPayloadCodec;
use App\Secrets\SecretTypeRegistry;
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

final class ProjectController extends AbstractController
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly SecretPayloadCodec $payloadCodec,
        private readonly SecretTypeRegistry $secretTypes,
        private readonly SecretRevealGate $revealGate,
    ) {
    }

    #[Route('/projects/invitation/{token}', name: 'app_project_invitation_accept', methods: ['GET', 'POST'])]
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

        $existingUser = $users->findOneBy(['email' => $invitation->getEmail()]);
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
    #[Route('/projects', name: 'app_project_index', methods: ['GET'])]
    public function index(ProjectRepository $projects): Response
    {
        return $this->render('project/index.html.twig', [
            'projects' => $projects->findAccessibleByUser($this->getCurrentUser()),
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/projects/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->getCurrentUser()->canCreateProjects()) {
            throw $this->createAccessDeniedException();
        }

        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getCurrentUser();
            $project->setCreatedBy($user);
            $project->addMember($user);

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
    #[Route('/projects/{id}', name: 'app_project_show', methods: ['GET'])]
    #[IsGranted(ProjectVoter::VIEW, subject: 'project')]
    public function show(Request $request, Project $project, UserRepository $users): Response
    {
        $secretPayloads = [];
        $revealedSecrets = [];
        $revealExpirations = [];
        $revealForms = [];
        $openRevealSecretId = trim((string) $request->query->get('reveal', ''));
        foreach ($project->getSecrets() as $secret) {
            $secretId = $secret->getIdString();
            $revealedSecrets[$secretId] = $this->revealGate->isGranted($request->getSession(), $secret);
            if ($revealedSecrets[$secretId]) {
                $secretPayloads[$secretId] = $this->payloadCodec->decode($secret);
                $revealExpirations[$secretId] = $this->revealGate->expiresAt($request->getSession(), $secret);
            }

            $revealForms[$secretId] = $this->createForm(SecretRevealType::class, null, [
                'action' => $this->generateUrl('app_secret_reveal', ['id' => $secretId]),
                'method' => 'POST',
            ])->createView();
        }

        $membersForm = null;
        if ($this->isGranted(ProjectVoter::EDIT, $project)) {
            $membersForm = $this->createForm(ProjectMembersType::class, null, [
                'member_choices' => $users->findAssignableByManager($this->getCurrentUser()),
                'selected_members' => $project->getMembers()->toArray(),
            ])->createView();
        }

        return $this->render('project/show.html.twig', [
            'project' => $project,
            'secretPayloads' => $secretPayloads,
            'secretTypes' => $this->secretTypes->all(),
            'membersForm' => $membersForm,
            'revealedSecrets' => $revealedSecrets,
            'revealExpirations' => $revealExpirations,
            'revealForms' => $revealForms,
            'openRevealSecretId' => $openRevealSecretId,
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/projects/{id}/edit', name: 'app_project_edit', methods: ['GET', 'POST'])]
    #[IsGranted(ProjectVoter::EDIT, subject: 'project')]
    public function edit(Request $request, Project $project, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project->addMember($project->getCreatedBy());
            $project->addMember($this->getCurrentUser());
            $em->flush();

            $this->addFlash('success', 'Projet mis à jour.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getIdString()]);
        }

        return $this->render('project/form.html.twig', [
            'title' => 'Modifier le projet',
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/projects/{id}/delete', name: 'app_project_delete', methods: ['POST'])]
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
    #[Route('/projects/{id}/members', name: 'app_project_members_update', methods: ['POST'])]
    #[IsGranted(ProjectVoter::EDIT, subject: 'project')]
    public function updateMembers(
        Request $request,
        Project $project,
        UserRepository $users,
        EntityManagerInterface $em,
    ): Response {
        $choices = $users->findAssignableByManager($this->getCurrentUser());
        $form = $this->createForm(ProjectMembersType::class, null, [
            'member_choices' => $choices,
            'selected_members' => $project->getMembers()->toArray(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedMembers = $this->normalizeSelectedUsers(
                $this->submittedFieldValues($request, $form, 'members') ?? $form->get('members')->getData(),
                $choices,
            );
            $selectedMemberIds = [];
            foreach ($selectedMembers as $member) {
                $selectedMemberIds[$member->getIdString()] = true;
            }

            foreach ($project->getMembers()->toArray() as $member) {
                if ($project->getCreatedBy()->getId()->equals($member->getId())) {
                    continue;
                }

                if (!isset($selectedMemberIds[$member->getIdString()])) {
                    $project->removeMember($member);
                }
            }

            $project->addMember($project->getCreatedBy());
            foreach ($selectedMembers as $member) {
                $project->addMember($member);
            }

            $em->flush();
            $this->addFlash('success', 'Affectations projet mises à jour.');
        } else {
            $this->addFlash('error', 'Impossible de mettre à jour les affectations du projet.');
        }

        return $this->redirectToRoute('app_project_show', ['id' => $project->getIdString()]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/projects/{id}/invitations/{invitation}/approve', name: 'app_project_invitation_approve', methods: ['POST'])]
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
    #[Route('/projects/{id}/invitations/{invitation}/revoke', name: 'app_project_invitation_revoke', methods: ['POST'])]
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

    private function getCurrentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
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
        if ($project->isManageableBy($user)) {
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

    /**
     * @param mixed $submitted
     * @param list<User> $choices
     * @return list<User>
     */
    private function normalizeSelectedUsers(mixed $submitted, array $choices): array
    {
        $byId = [];
        foreach ($choices as $choice) {
            $byId[$choice->getIdString()] = $choice;
        }

        $selected = [];
        foreach ($this->flattenSubmittedValues($submitted) as $value) {
            if ($value instanceof User) {
                $selected[$value->getIdString()] = $value;

                continue;
            }

            $id = is_scalar($value) ? trim((string) $value) : '';
            if ('' !== $id && isset($byId[$id])) {
                $selected[$id] = $byId[$id];
            }
        }

        return array_values($selected);
    }

    /**
     * @return list<mixed>
     */
    private function flattenSubmittedValues(mixed $submitted): array
    {
        if (null === $submitted) {
            return [];
        }

        if ($submitted instanceof \Traversable) {
            $submitted = iterator_to_array($submitted);
        }

        if (!is_array($submitted)) {
            return [$submitted];
        }

        $flattened = [];
        foreach ($submitted as $value) {
            foreach ($this->flattenSubmittedValues($value) as $child) {
                $flattened[] = $child;
            }
        }

        return $flattened;
    }

    private function submittedFieldValues(Request $request, FormInterface $form, string $field): mixed
    {
        $rootName = $form->getName();
        if ('' === $rootName) {
            $payload = $request->request->all();

            return is_array($payload) ? ($payload[$field] ?? null) : null;
        }

        $payload = $request->request->all($rootName);

        return is_array($payload) ? ($payload[$field] ?? null) : null;
    }
}
