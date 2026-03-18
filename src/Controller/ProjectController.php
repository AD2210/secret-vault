<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Form\ProjectType;
use App\Repository\ProjectRepository;
use App\Security\ProjectVoter;
use App\Security\VaultCipher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/projects')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ProjectController extends AbstractController
{
    public function __construct(private readonly VaultCipher $cipher)
    {
    }

    #[Route('', name: 'app_project_index', methods: ['GET'])]
    public function index(ProjectRepository $projects): Response
    {
        return $this->render('project/index.html.twig', [
            'projects' => $projects->findAccessibleByUser($this->getCurrentUser()),
        ]);
    }

    #[Route('/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project, [
            'plaintext_defaults' => [],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getCurrentUser();
            $project->setCreatedBy($user);
            $project->addMember($user);
            $this->hydrateEncryptedFields($project, $form);

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

    #[Route('/{id}/edit', name: 'app_project_edit', methods: ['GET', 'POST'])]
    #[IsGranted(ProjectVoter::EDIT, subject: 'project')]
    public function edit(Request $request, Project $project, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProjectType::class, $project, [
            'plaintext_defaults' => $this->decryptProject($project),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project->addMember($this->getCurrentUser());
            $this->hydrateEncryptedFields($project, $form);
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
}
