<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProjectAccessTest extends WebTestCase
{
    protected function setUp(): void
    {
        putenv('SYMFONY_TRUSTED_PROXIES=private_ranges');
        putenv('VAULT_ENCRYPTION_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
        putenv('CHILD_APP_PROVISIONING_TOKEN=test-provisioning-token');
        putenv('DEFAULT_URI=http://localhost');
        putenv('DATABASE_URL=sqlite:///%kernel.project_dir%/var/data_test.db');

        self::ensureKernelShutdown();
        static::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $classes = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
        self::ensureKernelShutdown();
    }

    public function testProjectIndexShowsProjectCreatedByCurrentUser(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User('owner@example.com', 'Owner', 'User'))
            ->setRoles(['ROLE_USER'])
            ->setPassword('$2y$13$dummyhashdummyhashdummyhashdummyhashdummyhashdummyhash');

        $project = (new Project('Project Alpha', 'Client One'))
            ->setCreatedBy($user);
        $project->addMember($user);

        $em->persist($user);
        $em->persist($project);
        $em->flush();
        $em->clear();

        $client->loginUser($user);
        $client->request('GET', '/projects');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Project Alpha');
    }
}
