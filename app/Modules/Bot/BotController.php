<?php

namespace App\Modules\Bot;

use App\Core\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Module 1 (Bot Gateway) endpoints. Service-key protected; `me` also requires
 * a resolved acting account.
 */
class BotController
{
    public function ping(Request $request, Response $response): Response
    {
        return ResponseHelper::json($response, [
            'status'        => 'ok',
            'utc_time'      => IranDay::nowUtc(),
            'iran_date'     => IranDay::today(),
            'iran_date_jalali' => IranDay::jalali(),
        ]);
    }

    public function me(Request $request, Response $response): Response
    {
        /** @var array $actor */
        $actor = $request->getAttribute('bot_actor');

        return ResponseHelper::json($response, [
            'role'       => $actor['role'],
            'account_id' => $actor['account_id'],
            'name'       => $actor['name'],
            'telegram_user_id' => (int) $actor['link']['telegram_user_id'],
            'chat_id'    => (int) $actor['link']['chat_id'],
            'is_blocked' => (int) $actor['link']['is_blocked'] === 1,
            'linked_at'  => $actor['link']['linked_at'],
        ]);
    }
}
