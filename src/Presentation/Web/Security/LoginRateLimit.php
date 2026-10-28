<?php

declare(strict_types=1);

namespace App\Presentation\Web\Security;

use App\Presentation\Web\AuthPage;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 25)]
final readonly class LoginRateLimit
{
    private const array ROUTES = ['login', 'login_check'];

    public function __construct(
        #[Target('login')]
        private RateLimiterFactoryInterface $limiter,
        private AuthPage $page,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !\in_array($request->attributes->get('_route'), self::ROUTES, true)) {
            return;
        }

        $limit = $this->limiter->create($request->getClientIp())->consume();
        if ($limit->isAccepted()) {
            return;
        }

        $response = $this->page->render('LoginPage', ['error' => $this->translator->trans('login.too_many_attempts')], Response::HTTP_TOO_MANY_REQUESTS);
        $response->headers->set('Retry-After', (string) max(1, $limit->getRetryAfter()->getTimestamp() - time()));
        $event->setResponse($response);
    }
}
