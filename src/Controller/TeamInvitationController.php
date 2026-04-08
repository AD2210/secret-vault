<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\InvitationRegistrationType;
use App\Repository\UserInvitationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class TeamInvitationController extends AbstractController
{
    #[Route('/team/invitation/{token}', name: 'app_team_invitation_accept', methods: ['GET', 'POST'])]
    public function accept(
        string $token,
        Request $request,
        UserInvitationRepository $invitations,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): Response {
        $invitation = $invitations->findOneByPlainToken($token);
        if (null === $invitation) {
            throw $this->createNotFoundException();
        }

        if ($invitation->isRevoked()) {
            return $this->render('team/invitation_status.html.twig', [
                'invitation' => $invitation,
                'state' => 'revoked',
            ]);
        }

        if ($invitation->isExpired()) {
            return $this->render('team/invitation_status.html.twig', [
                'invitation' => $invitation,
                'state' => 'expired',
            ]);
        }

        if ($invitation->isAccepted()) {
            return $this->render('team/invitation_status.html.twig', [
                'invitation' => $invitation,
                'state' => 'accepted',
            ]);
        }

        if (null !== $users->findOneBy(['email' => $invitation->getEmail()])) {
            return $this->render('team/invitation_status.html.twig', [
                'invitation' => $invitation,
                'state' => 'existing_account',
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
            $user
                ->setPrimaryRole($invitation->getRole())
                ->setPassword($hasher->hashPassword($user, $plainPassword));

            $em->persist($user);
            $invitation
                ->setInviteeUser($user)
                ->markAccepted();
            $em->flush();

            $this->addFlash('success', 'Compte créé. Vous pouvez maintenant vous connecter.');

            return $this->redirectToRoute('app_login', [
                'email' => $invitation->getEmail(),
            ]);
        }

        return $this->render('team/invitation_register.html.twig', [
            'invitation' => $invitation,
            'form' => $form,
        ]);
    }
}
