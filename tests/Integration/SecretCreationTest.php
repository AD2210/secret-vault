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

final class SecretCreationTest extends WebTestCase
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

    public function testLeadCanCreatePasswordSecret(): void
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

        $em->persist($user);
        $em->persist($project);
        $em->flush();
        $em->clear();

        $client->loginUser($user);
        $crawler = $client->request('GET', sprintf('/projects/%s/secrets/new?type=password', $project->getIdString()));

        self::assertResponseIsSuccessful();

        $client->submit($crawler->selectButton('Enregistrer')->form([
            'secret[name]' => 'Production DB',
            'secret[username]' => 'db-user',
            'secret[password]' => 'S3cr3t!',
        ]));

        self::assertResponseRedirects(sprintf('/projects/%s', $project->getIdString()));

        $em->clear();
        $secret = $em->getRepository(Secret::class)->findOneBy(['name' => 'Production DB']);

        self::assertInstanceOf(Secret::class, $secret);
        self::assertSame(Secret::TYPE_PASSWORD, $secret->getType());
        self::assertNotNull($secret->getPayloadEncrypted());
        self::assertNull($secret->getPublicSecretEncrypted());
        self::assertNull($secret->getPrivateSecretEncrypted());
        self::assertNotNull($secret->getEncryptionKeyVersion());

        /** @var SecretPayloadCodec $codec */
        $codec = static::getContainer()->get(SecretPayloadCodec::class);
        self::assertSame([
            'username' => 'db-user',
            'password' => 'S3cr3t!',
        ], $codec->decode($secret));
    }
}
