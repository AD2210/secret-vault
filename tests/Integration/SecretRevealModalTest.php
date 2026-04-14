<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Project;
use App\Entity\Secret;
use App\Entity\User;
use App\Secrets\SecretPayloadCodec;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecretRevealModalTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        static::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $classes = $em->getMetadataFactory()->getAllMetadata();

        $tool->dropSchema($classes);
        $tool->createSchema($classes);

        self::ensureKernelShutdown();
    }

    public function testRevealLinkFallsBackToQueryDrivenModalOpen(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User('lead@example.com', 'Lead', 'User'))
            ->setPrimaryRole(User::ROLE_LEAD)
            ->setPassword('$2y$13$dummyhashdummyhashdummyhashdummyhashdummyhashdummyhash')
            ->prepareTotp('JBSWY3DPEHPK3PXP')
            ->enableTotp();

        $project = (new Project('Project Alpha', 'Client One'))
            ->setCreatedBy($user);
        $project->addMember($user);

        $secret = (new Secret('Production DB'))
            ->setProject($project)
            ->setType(Secret::TYPE_PASSWORD)
            ->setCreatedBy($user);

        /** @var SecretPayloadCodec $codec */
        $codec = static::getContainer()->get(SecretPayloadCodec::class);
        $codec->encodeIntoSecret($secret, [
            'username' => 'db-user',
            'password' => 'S3cr3t!',
        ]);

        $em->persist($user);
        $em->persist($project);
        $em->persist($secret);
        $em->flush();
        $em->clear();

        $client->loginUser($user);
        $crawler = $client->request('GET', sprintf('/projects/%s', $project->getIdString()));

        self::assertResponseIsSuccessful();
        self::assertStringEndsWith(
            sprintf('/projects/%s?reveal=%s', $project->getIdString(), $secret->getIdString()),
            $crawler->selectLink('Révéler 60s')->link()->getUri(),
        );

        $client->request('GET', sprintf('/projects/%s?reveal=%s', $project->getIdString(), $secret->getIdString()));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('.modal.is-open #reveal-title-%s', $secret->getIdString()));
    }
}
