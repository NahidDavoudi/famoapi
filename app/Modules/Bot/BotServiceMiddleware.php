<?php

namespace App\Modules\Bot;

use App\Core\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Service-key authentication for the external bot host.
 *
 * Completely separate from user JWT auth: the key grants access only to the
 * /api/v1/bot/* routes it is mounted on. An optional IP allow-list restricts
 * callers further. The key is read from the environment only.
 */
final class BotServiceMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $configuredKey = (string) ($_ENV['BOT_SERVICE_KEY'] ?? '');
        if ($configuredKey === '') {
            return $this->fail('BOT_NOT_CONFIGURED', 'کلید سرویس ربات تنظیم نشده است', 500);
        }

        $providedKey = $request->getHeaderLine('X-Bot-Key');
        if ($providedKey === '' || !hash_equals($configuredKey, $providedKey)) {
            return $this->fail('BOT_UNAUTHORIZED', 'کلید سرویس ربات نامعتبر است', 401);
        }

        $allowList = trim((string) ($_ENV['BOT_IP_ALLOWLIST'] ?? ''));
        if ($allowList !== '') {
            $allowed = array_values(array_filter(array_map('trim', explode(',', $allowList)), static fn ($ip) => $ip !== ''));
            if (!in_array($this->clientIp($request), $allowed, true)) {
                return $this->fail('BOT_IP_FORBIDDEN', 'آی‌پی درخواست‌کننده مجاز نیست', 403);
            }
        }

        return $handler->handle($request);
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        $server = $request->getServerParams();
        $remote = trim((string) ($server['REMOTE_ADDR'] ?? ''));
        if ($remote !== '') {
            return $remote;
        }

        // Fallback for proxied deployments where REMOTE_ADDR is unavailable.
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded !== '') {
            return trim(explode(',', $forwarded)[0]);
        }

        return '';
    }

    private function fail(string $code, string $message, int $status): ResponseInterface
    {
        return ResponseHelper::error(new Response(), $code, $message, $status);
    }
}
