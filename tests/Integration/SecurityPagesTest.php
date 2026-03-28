<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityPagesTest extends WebTestCase
{
    private const TENANT_SLUG = 'acme-demo';

    public function testLoginPageIsReachable(): void
    {
        $client = static::createClient();
        $client->request('GET', sprintf('/t/%s/login', self::TENANT_SLUG));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Déverrouiller le coffre');
    }

    public function testLoginPagePrefillsEmailFromQueryString(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', sprintf('/t/%s/login?email=owner%%40example.com', self::TENANT_SLUG));

        self::assertResponseIsSuccessful();
        self::assertSame('owner@example.com', $crawler->filter('#username')->attr('value'));
    }

    public function testDashboardRedirectsAnonymousUsersToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', sprintf('/t/%s', self::TENANT_SLUG));

        self::assertResponseRedirects(sprintf('/t/%s/login', self::TENANT_SLUG));
    }
}
