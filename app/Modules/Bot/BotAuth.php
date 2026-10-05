<?php
namespace App\Modules\Bot;

use App\Core\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class BotAuth implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $role      = strtolower(trim($request->getHeaderLine('X-Bot-Role')));
        $accountId = (int) $request->getHeaderLine('X-Bot-Account-Id');
        $chatId    = (int) $request->getHeaderLine('X-Telegram-Chat-Id');
        $name      = trim($request->getHeaderLine('X-Bot-Account-Name'));

        if (!in_array($role, ['student', 'supporter', 'admin'], true) || $accountId <= 0) {
            return ResponseHelper::error(new Response(), 'BOT_ACTOR_MISSING', 'هدرهای ربات ناقص است', 400);
        }

        return $handler->handle($request->withAttribute('bot_actor', [
            'role'       => $role,
            'account_id' => $accountId,
            'name'       => $name,
            'chat_id'    => $chatId,
        ]));
    }
}