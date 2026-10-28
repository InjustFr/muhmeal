<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Tests\Support\SignsInClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityHeadersTest extends WebTestCase
{
    use SignsInClient;

    private const string POLICY = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self' https://accounts.test";

    public function testTheShellCarriesTheHeaders(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSecurityHeaders();
    }

    public function testApiResponsesCarryTheHeaders(): void
    {
        $client = self::createClient(['debug' => false]);

        $client->jsonRequest('GET', '/api/ingredients');

        self::assertSecurityHeaders();
    }

    public function testTheLoginRedirectCarriesTheHeaders(): void
    {
        $client = self::createClient();

        $client->request('GET', '/login');

        self::assertResponseRedirects();
        self::assertSecurityHeaders();
    }

    public function testTheSignInErrorPageCarriesTheHeaders(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');
        $client->request('GET', '/login/check', ['error' => 'access_denied']);

        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSecurityHeaders();
    }

    public function testStrictTransportSecurityOnlyComesOverHttps(): void
    {
        $client = self::createClient();

        $client->request('GET', '/login');
        self::assertResponseNotHasHeader('Strict-Transport-Security');

        $client->request('GET', '/login', server: ['HTTPS' => 'on']);
        self::assertResponseHeaderSame('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    private static function assertSecurityHeaders(): void
    {
        self::assertResponseHeaderSame('Content-Security-Policy', self::POLICY);
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Referrer-Policy', 'same-origin');
        self::assertResponseHeaderSame('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        self::assertResponseHeaderSame('Cross-Origin-Opener-Policy', 'same-origin');
        self::assertResponseHeaderSame('Cross-Origin-Resource-Policy', 'same-origin');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
