<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginRateLimitTest extends WebTestCase
{
    public function testTooManySignInAttemptsFromOneAddressAreRefused(): void
    {
        $client = self::createClient();
        $client->disableReboot();

        for ($attempt = 0; $attempt < 30; ++$attempt) {
            $client->request('GET', '/login');
            self::assertResponseRedirects();
        }

        $crawler = $client->request('GET', '/login');
        self::assertResponseStatusCodeSame(429);
        self::assertGreaterThan(0, (int) $client->getResponse()->headers->get('Retry-After'));
        self::assertSame('Too many sign-in attempts, please wait a few minutes and try again.', Json::string(Json::decode((string) $crawler->filter('#auth')->attr('data-props')), 'error'));

        $client->request('GET', '/login/check', ['state' => 'forged', 'code' => 'forged']);
        self::assertResponseStatusCodeSame(429);
    }

    public function testOtherAddressesAreNotAffected(): void
    {
        $client = self::createClient();
        $client->disableReboot();

        for ($attempt = 0; $attempt < 31; ++$attempt) {
            $client->request('GET', '/login');
        }
        self::assertResponseStatusCodeSame(429);

        $client->request('GET', '/login', server: ['REMOTE_ADDR' => '203.0.113.7']);
        self::assertResponseRedirects();
    }
}
