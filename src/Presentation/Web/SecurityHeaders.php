<?php

declare(strict_types=1);

namespace App\Presentation\Web;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class SecurityHeaders
{
    private const array ALWAYS = [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Cross-Origin-Resource-Policy' => 'same-origin',
        'X-Frame-Options' => 'DENY',
        'X-Robots-Tag' => 'noindex, nofollow, noarchive',
    ];

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $environment,
        #[Autowire(env: 'ACCOUNTS_URL')]
        private string $accountsUrl,
    ) {
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->add(self::ALWAYS);
        if ('dev' !== $this->environment) {
            $headers->set('Content-Security-Policy', $this->policy());
        }
        if ($event->getRequest()->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }

    private function policy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'".$this->accountsOrigin(),
        ]);
    }

    private function accountsOrigin(): string
    {
        $url = parse_url($this->accountsUrl);
        if (!isset($url['scheme'], $url['host'])) {
            return '';
        }

        return \sprintf(' %s://%s%s', $url['scheme'], $url['host'], isset($url['port']) ? ':'.$url['port'] : '');
    }
}
