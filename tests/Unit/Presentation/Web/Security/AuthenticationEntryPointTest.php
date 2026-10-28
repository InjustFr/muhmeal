<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Security;

use App\Presentation\Web\Security\AuthenticationEntryPoint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\IdentityTranslator;

final class AuthenticationEntryPointTest extends TestCase
{
    public function testSignedOutApiCallsAreUnauthorized(): void
    {
        $response = $this->entryPoint()->start(Request::create('/api/ingredients'));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
    }

    public function testSignedOutPagesSendToLogin(): void
    {
        $response = $this->entryPoint()->start(Request::create('/pantry'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/login', $response->getTargetUrl());
    }

    private function entryPoint(): AuthenticationEntryPoint
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/login');

        return new AuthenticationEntryPoint($urls, new IdentityTranslator());
    }
}
