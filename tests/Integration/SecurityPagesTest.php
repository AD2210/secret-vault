<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityPagesTest extends WebTestCase
{
    public function testLoginPageIsReachable(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Déverrouiller le coffre');
    }

    public function testLoginPagePrefillsEmailFromQueryString(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login?email=owner%40example.com');

        self::assertResponseIsSuccessful();
        self::assertSame('owner@example.com', $crawler->filter('#username')->attr('value'));
    }

    public function testDashboardRedirectsAnonymousUsersToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }
}
