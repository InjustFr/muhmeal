<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Api;

use App\Domain\Identity\Exception\EmptyDisplayName;
use App\Domain\Shared\Exception\NotFound;
use App\Presentation\Api\DomainExceptionListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Translation\IdentityTranslator;

final class DomainExceptionListenerTest extends TestCase
{
    public function testARuleViolationAnswersProblemJsonWithStatus422(): void
    {
        $response = $this->handle(new EmptyDisplayName())->getResponse();

        self::assertSame(422, $response?->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame(['title' => 'problem.business_rule', 'status' => 422, 'detail' => 'user.empty_display_name'], json_decode((string) $response->getContent(), true));
    }

    public function testAMissingThingAnswersStatus404(): void
    {
        self::assertSame(404, $this->handle(new NotFound('user', 'abc'))->getResponse()?->getStatusCode());
    }

    public function testOtherExceptionsPassThrough(): void
    {
        self::assertFalse($this->handle(new \RuntimeException('boom'))->hasResponse());
    }

    private function handle(\Throwable $exception): ExceptionEvent
    {
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create('/api/ingredients'), HttpKernelInterface::MAIN_REQUEST, $exception);
        new DomainExceptionListener(new IdentityTranslator())($event);

        return $event;
    }
}
