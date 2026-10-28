<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Api;

use App\Presentation\Api\SameOriginGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Translation\IdentityTranslator;

final class SameOriginGuardTest extends TestCase
{
    /**
     * @param array<string, string> $server
     */
    #[DataProvider('crossSiteWrites')]
    public function testCrossSiteWritesAreForbidden(array $server): void
    {
        $event = $this->guard('/api/ingredients', 'POST', $server);

        self::assertSame(403, $event->getResponse()?->getStatusCode());
        self::assertSame('application/problem+json', $event->getResponse()->headers->get('Content-Type'));
    }

    /** @return iterable<array{array<string, string>}> */
    public static function crossSiteWrites(): iterable
    {
        yield 'fetch metadata' => [['HTTP_SEC_FETCH_SITE' => 'cross-site']];
        yield 'same site but another origin' => [['HTTP_SEC_FETCH_SITE' => 'same-site']];
        yield 'origin header' => [['HTTP_ORIGIN' => 'https://evil.example']];
    }

    #[DataProvider('encodedApiPaths')]
    public function testEncodedApiPathsAreGuarded(string $path): void
    {
        $event = $this->guard($path, 'POST', ['HTTP_SEC_FETCH_SITE' => 'same-site']);

        self::assertSame(403, $event->getResponse()?->getStatusCode());
    }

    /** @return iterable<array{string}> */
    public static function encodedApiPaths(): iterable
    {
        yield 'encoded letter' => ['/%61pi/ingredients'];
        yield 'encoded slash' => ['/api%2Fingredients'];
    }

    /**
     * @param array<string, string> $server
     */
    #[DataProvider('sameOriginWrites')]
    public function testSameOriginWritesPass(array $server): void
    {
        self::assertFalse($this->guard('/api/ingredients', 'DELETE', $server)->hasResponse());
    }

    /** @return iterable<array{array<string, string>}> */
    public static function sameOriginWrites(): iterable
    {
        yield 'no header' => [[]];
        yield 'fetch metadata' => [['HTTP_SEC_FETCH_SITE' => 'same-origin']];
        yield 'typed address' => [['HTTP_SEC_FETCH_SITE' => 'none']];
        yield 'origin header' => [['HTTP_ORIGIN' => 'http://localhost']];
    }

    public function testCrossSiteReadsAreLeftToTheSession(): void
    {
        self::assertFalse($this->guard('/api/ingredients', 'GET', ['HTTP_SEC_FETCH_SITE' => 'cross-site'])->hasResponse());
    }

    public function testPagesAreNotGuarded(): void
    {
        self::assertFalse($this->guard('/logout', 'POST', ['HTTP_SEC_FETCH_SITE' => 'cross-site'])->hasResponse());
    }

    /**
     * @param array<string, string> $server
     */
    private function guard(string $path, string $method, array $server): RequestEvent
    {
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), Request::create($path, $method, server: $server), HttpKernelInterface::MAIN_REQUEST);
        new SameOriginGuard(new IdentityTranslator())($event);

        return $event;
    }
}
