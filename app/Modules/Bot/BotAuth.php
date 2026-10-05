<?php
declare(strict_types=1);

namespace App\Modules\Bot;

use App\Core\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Resolves the acting account for /bot/* endpoints that operate on behalf of
 * a linked user. Requires BotServiceMiddleware to have run first.
 *
 * Expected headers (set by the bot host):
 *   X-Bot-Role: student|supporter|admin
 *   X-Bot-Account-Id: <int>          (preferred)
 *   X-Telegram-Chat-Id: <int>
 *   X-Bot-Account-Name: <string>     (optional)
 *
 * Fallback: if X-Bot-Account-Id is missing, the link is looked up by chat_id.
 */
final class BotAuth implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $role      = strtolower(trim($request->getHeaderLine('X-Bot-Role')));
        $accountId = (int) $request->getHeaderLine('X-Bot-Account-Id');
        $chatId    = (int) $request->getHeaderLine('X-Telegram-Chat-Id');
        $name      = trim($request->getHeaderLine('X-Bot-Account-Name'));

        if (!in_array($role, ['student', 'supporter', 'admin'], true)) {
            return $this->fail('BOT_ACTOR_MISSING', 'نقش ربات نامعتبر است', 400);
        }

        // Fallback: اگر ربات account_id نفرستاده، از chat_id پیدا کن.
        if ($accountId <= 0 && $chatId > 0) {
            $dbRoles = $role === 'supporter' ? ['supporter', 'admin'] : [$role];
            $link = TelegramLink::findByChatIdAndRoles($chatId, $dbRoles);
            if ($link) {
                $accountId = (int) $link['account_id'];
                if ($name === '') {
                    $name = (string) ($link['name'] ?? '');
                }
            }
        }

        if ($accountId <= 0) {
            return $this->fail('BOT_NOT_LINKED', 'این حساب تلگرام به کاربری متصل نیست', 401);
        }

        return $handler->handle($request->withAttribute('bot_actor', [
            'role'       => $role,
            'account_id' => $accountId,
            'name'       => $name,
            'chat_id'    => $chatId,
        ]));
    }

    private function fail(string $code, string $message, int $status): ResponseInterface
    {
        return ResponseHelper::error(new Response(), $code, $message, $status);
    }
}