<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\UserInvitation;
use App\Form\TeamInvitationType;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\ProjectRepository;
use App\Repository\UserInvitationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TeamController extends AbstractController
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    #[Route('/team', name: 'app_team_index', methods: ['GET'])]
    public function index(UserRepository $users, UserInvitationRepository $invitations): Response
    {
        $this->denyUnlessCanManageUsers();

        return $this->render('team/index.html.twig', [
            'users' => $users->findAllOrdered(),
            'pendingInvitations' => $invitations->findPendingVisibleToManager($this->getCurrentUser()),
        ]);
    }

    #[Route('/team/new', name: 'app_team_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $users,
        UserInvitationRepository $invitations,
    ): Response
    {
        $this->denyUnlessCanManageUsers();

        $actor = $this->getCurrentUser();
        $invitation = new UserInvitation(
            $actor,
            '',
            User::ROLE_USER,
            hash('sha256', bin2hex(random_bytes(32))),
            new \DateTimeImmutable('+7 days'),
        );

        $form = $this->createForm(TeamInvitationType::class, $invitation, [
            'role_choices' => $this->roleChoicesForManager($actor),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (null !== $users->findOneBy(['email' => $invitation->getEmail()])) {
                $this->addFlash('error', 'Un compte existe déjà pour cet email.');

                return $this->redirectToRoute('app_team_new');
            }

            if ($invitations->hasPendingInvitationForEmail($invitation->getEmail())) {
                $this->addFlash('error', 'Une invitation est déjà active pour cet email.');

                return $this->redirectToRoute('app_team_new');
            }

            $plainToken = bin2hex(random_bytes(32));
            $invitation = new UserInvitation(
                $actor,
                $invitation->getEmail(),
                $invitation->getRole(),
                hash('sha256', $plainToken),
                new \DateTimeImmutable('+7 days'),
            );

            $em->persist($invitation);
            $em->flush();
            $this->sendInvitationEmail($invitation, $plainToken);

            $this->addFlash('success', 'Invitation envoyée.');

            return $this->redirectToRoute('app_team_index');
        }

        return $this->render('team/invite_form.html.twig', [
            'title' => 'Inviter un utilisateur',
            'form' => $form,
        ]);
    }

    #[Route('/team/{id}/edit', name: 'app_team_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        ProjectRepository $projects,
    ): Response
    {
        $this->denyUnlessCanManageUsers();
        $this->denyUnlessCanManageUser($user);

        $manageableProjects = $projects->findManageableByUser($this->getCurrentUser());
        $form = $this->createForm(UserType::class, $user, [
            'require_password' => false,
            'current_role' => $user->getPrimaryRole(),
            'role_choices' => $this->roleChoicesForManager($this->getCurrentUser()),
            'project_choices' => $manageableProjects,
            'selected_projects' => array_values(array_filter(
                $user->getProjects()->toArray(),
                static fn (\App\Entity\Project $project): bool => in_array($project, $manageableProjects, true),
            )),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPrimaryRole((string) $form->get('role')->getData());

            $plainPassword = $form->get('plainPassword')->getData();
            if (is_string($plainPassword) && '' !== $plainPassword) {
                $user->setPassword($hasher->hashPassword($user, $plainPassword));
            }

            if ((bool) $form->get('reset_totp')->getData()) {
                $user->disableTotp();
            }

            $this->synchronizeProjects(
                $user,
                $this->normalizeSelectedProjects($form->get('projects')->getData(), $manageableProjects),
                $manageableProjects,
            );
            $em->flush();

            $this->addFlash('success', 'Utilisateur mis à jour.');

            return $this->redirectToRoute('app_team_index');
        }

        return $this->render('team/form.html.twig', [
            'title' => 'Modifier l’utilisateur',
            'form' => $form,
        ]);
    }

    private function denyUnlessCanManageUsers(): void
    {
        if ($this->getCurrentUser()->canManageUsers()) {
            return;
        }

        throw $this->createAccessDeniedException();
    }

    private function denyUnlessCanManageUser(User $subject): void
    {
        $actor = $this->getCurrentUser();
        if ($actor->isAdmin()) {
            return;
        }

        if ($actor->isLead() && !$subject->isAdmin()) {
            return;
        }

        throw $this->createAccessDeniedException();
    }

    /**
     * @return array<string, string>
     */
    private function roleChoicesForManager(User $manager): array
    {
        $roles = User::roleLabels();
        if ($manager->isAdmin()) {
            return $roles;
        }

        unset($roles[User::ROLE_ADMIN]);

        return $roles;
    }

    #[Route('/team/invitations/{id}/revoke', name: 'app_team_invitation_revoke', methods: ['POST'])]
    public function revokeInvitation(Request $request, UserInvitation $invitation, EntityManagerInterface $em): Response
    {
        $this->denyUnlessCanManageUsers();

        if (
            !$this->getCurrentUser()->isAdmin()
            && !$invitation->getInvitedBy()->getId()->equals($this->getCurrentUser()->getId())
        ) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('revoke-team-invitation-'.$invitation->getIdString(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $invitation->revoke();
        $em->flush();

        $this->addFlash('success', 'Invitation révoquée.');

        return $this->redirectToRoute('app_team_index');
    }

    /**
     * @param list<\App\Entity\Project> $selectedProjects
     * @param list<\App\Entity\Project> $manageableProjects
     */
    private function synchronizeProjects(User $user, array $selectedProjects, array $manageableProjects): void
    {
        foreach ($manageableProjects as $project) {
            $user->removeProject($project);
        }

        foreach ($selectedProjects as $project) {
            if (in_array($project, $manageableProjects, true)) {
                $user->addProject($project);
            }
        }
    }

    /**
     * @param mixed $submitted
     * @param list<\App\Entity\Project> $choices
     * @return list<\App\Entity\Project>
     */
    private function normalizeSelectedProjects(mixed $submitted, array $choices): array
    {
        $byId = [];
        foreach ($choices as $choice) {
            $byId[$choice->getIdString()] = $choice;
        }

        $selected = [];
        foreach ($this->flattenSubmittedValues($submitted) as $value) {
            if ($value instanceof \App\Entity\Project) {
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

    private function getCurrentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function sendInvitationEmail(UserInvitation $invitation, string $plainToken): void
    {
        $this->mailer->send((new TemplatedEmail())
            ->to(new Address($invitation->getEmail()))
            ->subject('Invitation Client Secrets Vault')
            ->htmlTemplate('team/emails/invitation.html.twig')
            ->context([
                'invitation' => $invitation,
                'acceptUrl' => $this->generateUrl('app_team_invitation_accept', [
                    'token' => $plainToken,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ]));
    }
}
