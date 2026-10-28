<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web;

use App\Presentation\Web\SecurityHeaders;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SecurityHeadersTest extends TestCase
{
    public function testTheViteDevServerNeedsNoContentSecurityPolicy(): void
    {
        $response = $this->respond(new SecurityHeaders('dev', 'http://localhost:8096/mossyleaf'));

        self::assertFalse($response->headers->has('Content-Security-Policy'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function testFormsMayPostToTheAccountsOriginWithItsPort(): void
    {
        $response = $this->respond(new SecurityHeaders('prod', 'http://localhost:8096/mossyleaf'));

        self::assertStringEndsWith("form-action 'self' http://localhost:8096", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function testFormsStayOnTheAppWhenTheAccountsUrlIsNotAnUrl(): void
    {
        $response = $this->respond(new SecurityHeaders('prod', ''));

        self::assertStringEndsWith("form-action 'self'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function testSubRequestsAreLeftAlone(): void
    {
        $response = $this->respond(new SecurityHeaders('prod', 'https://accounts.test'), HttpKernelInterface::SUB_REQUEST);

        self::assertCount(0, array_intersect(['content-security-policy', 'x-content-type-options', 'x-robots-tag'], array_keys($response->headers->all())));
    }

    private function respond(SecurityHeaders $listener, int $type = HttpKernelInterface::MAIN_REQUEST): Response
    {
        $response = new Response();
        $listener(new ResponseEvent($this->createStub(HttpKernelInterface::class), Request::create('/'), $type, $response));

        return $response;
    }
}
