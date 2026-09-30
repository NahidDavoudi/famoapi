<?php

namespace App\Modules\Bot;

use App\Core\ApiException;
use App\Core\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Resolves the acting account for bot endpoints that operate "on behalf of" a
 * linked user. Requires the service key middleware to have run first.
 *
 * Expected headers:
 *   X-Bot-Role: student|supporter
 *   X-Telegram-User-Id: <int>
 *   X-Telegram-Chat-Id: <int>
 *
 * On success the request attribute `bot_actor` is set. Role/ownership checks
 * for the specific endpoint are performed by the endpoint itself.
 */
final class BotActorMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $role = strtolower(trim($request->getHeaderLine('X-Bot-Role')));
        $telegramUserId = trim($request->getHeaderLine('X-Telegram-User-Id'));
        $chatId = trim($request->getHeaderLine('X-Telegram-Chat-Id'));

        if ($role === '' || $telegramUserId === '' || $chatId === '') {
            return $this->fail('BOT_ACTOR_MISSING', 'شناسه کاربر ربات ارسال نشده است', 400);
        }

        if (!ctype_digit($telegramUserId) || !ctype_digit($chatId)) {
            return $this->fail('BOT_ACTOR_INVALID', 'شناسه کاربر ربات نامعتبر است', 400);
        }

        try {
            $actor = BotActor::resolve($role, (int) $telegramUserId, (int) $chatId);
        } catch (ApiException $e) {
            return $this->fail($e->getErrorCode(), $e->getMessage(), $e->getHttpStatus());
        }

        return $handler->handle($request->withAttribute('bot_actor', $actor));
    }

    private function fail(string $code, string $message, int $status): ResponseInterface
    {
        return ResponseHelper::error(new Response(), $code, $message, $status);
    }
}
