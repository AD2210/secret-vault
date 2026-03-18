<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/team')]
#[IsGranted('ROLE_ADMIN')]
final class TeamController extends AbstractController
{
    #[Route('', name: 'app_team_index', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('team/index.html.twig', [
            'users' => $users->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'app_team_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $user = new User();
        $user->setIsActive(true);

        $form = $this->createForm(UserType::class, $user, [
            'require_password' => true,
            'is_admin' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles($form->get('is_admin')->getData() ? ['ROLE_ADMIN'] : []);
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $user->setPassword($hasher->hashPassword($user, $plainPassword));

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Utilisateur créé. Il devra activer son 2FA à la première connexion.');

            return $this->redirectToRoute('app_team_index');
        }

        return $this->render('team/form.html.twig', [
            'title' => 'Nouvel utilisateur',
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_team_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $form = $this->createForm(UserType::class, $user, [
            'require_password' => false,
            'is_admin' => $user->isAdmin(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles($form->get('is_admin')->getData() ? ['ROLE_ADMIN'] : []);

            $plainPassword = $form->get('plainPassword')->getData();
            if (is_string($plainPassword) && '' !== $plainPassword) {
                $user->setPassword($hasher->hashPassword($user, $plainPassword));
            }

            if ((bool) $form->get('reset_totp')->getData()) {
                $user->disableTotp();
            }

            $em->flush();

            $this->addFlash('success', 'Utilisateur mis à jour.');

            return $this->redirectToRoute('app_team_index');
        }

        return $this->render('team/form.html.twig', [
            'title' => 'Modifier l’utilisateur',
            'form' => $form,
        ]);
    }
}
