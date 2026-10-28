<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation;

use App\Presentation\LocaleListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Translation\LocaleSwitcher;

final class LocaleListenerTest extends TestCase
{
    #[DataProvider('visitors')]
    public function testTheLocaleComesFromTheCookieThenTheBrowserThenEnglish(?string $cookie, ?string $acceptLanguage, string $expected): void
    {
        $request = Request::create('/', cookies: null === $cookie ? [] : [LocaleListener::COOKIE => $cookie], server: null === $acceptLanguage ? [] : ['HTTP_ACCEPT_LANGUAGE' => $acceptLanguage]);
        $locales = new LocaleSwitcher('en', []);

        new LocaleListener($locales)(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame([$expected, $expected], [$request->getLocale(), $locales->getLocale()]);
    }

    /** @return iterable<array{?string, ?string, string}> */
    public static function visitors(): iterable
    {
        yield 'the cookie wins over the browser' => ['fr', 'en-GB,en;q=0.9', 'fr'];
        yield 'a french browser' => [null, 'fr-FR,fr;q=0.9,en;q=0.8', 'fr'];
        yield 'an english browser' => [null, 'en-US,en;q=0.9', 'en'];
        yield 'an unsupported cookie falls back to the browser' => ['de', 'fr-FR', 'fr'];
        yield 'an unsupported browser falls back to english' => [null, 'de-DE,de;q=0.9', 'en'];
        yield 'no hint at all' => [null, null, 'en'];
    }

    public function testSubRequestsKeepTheLocaleOfTheirPage(): void
    {
        $request = Request::create('/', cookies: [LocaleListener::COOKIE => 'fr']);
        $locales = new LocaleSwitcher('en', []);

        new LocaleListener($locales)(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::SUB_REQUEST));

        self::assertSame(['en', 'en'], [$request->getLocale(), $locales->getLocale()]);
    }
}
