<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityPagesTest extends WebTestCase
{
    public function testBaseDomainLoginPageIsReachable(): void
    {
        $client = $this->createPreparedClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Déverrouiller le coffre');
    }

    public function testAnonymousProjectRouteRedirectsToLogin(): void
    {
        $client = $this->createPreparedClient();
        $client->request('GET', '/projects');

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testLoginPagePrefillsEmailFromQueryString(): void
    {
        $client = $this->createPreparedClient();
        $crawler = $client->request('GET', '/login?email=owner%40example.com');

        self::assertResponseIsSuccessful();
        self::assertSame('owner@example.com', $crawler->filter('#username')->attr('value'));
    }

    public function testSuccessfulLoginRedirectsToDashboard(): void
    {
        $client = $this->createPreparedClient();
        $this->createUser('admin@example.com', 'StrongPassword123!', enableTotp: false);

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Entrer dans le vault')->form([
            '_username' => 'admin@example.com',
            '_password' => 'StrongPassword123!',
        ]));

        self::assertResponseRedirects('/');
    }

    public function testDashboardRedirectsAnonymousUsersToLogin(): void
    {
        $client = $this->createPreparedClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    private function createUser(string $email, string $plainPassword, bool $enableTotp = true): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User($email, 'Ada', 'Lovelace');
        $user->setPassword($hasher->hashPassword($user, $plainPassword));
        if ($enableTotp) {
            $user->prepareTotp('JBSWY3DPEHPK3PXP');
            $user->enableTotp();
        }

        $em->persist($user);
        $em->flush();
    }

    private function createPreparedClient(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        self::ensureKernelShutdown();
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $classes = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($classes);
        $tool->createSchema($classes);

        return $client;
    }
}
