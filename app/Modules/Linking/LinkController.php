<?php

namespace App\Modules\Linking;

use App\Core\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Module 2: bot identity & linking endpoints. All are service-key protected
 * (mounted under /api/v1/bot). ApiException from the service is turned into the
 * standard error envelope by the global ExceptionHandler.
 */
class LinkController
{
    public function __construct(private LinkService $service)
    {
    }

    public function lookup(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $phone = (string) ($body['phone'] ?? '');
        $telegramUserId = isset($body['telegram_user_id']) ? (int) $body['telegram_user_id'] : null;

        return ResponseHelper::json($response, $this->service->lookup($phone, $telegramUserId));
    }

    public function link(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];

        return ResponseHelper::json($response, $this->service->link($body));
    }

    public function resolve(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $telegramUserId = isset($query['telegram_user_id']) ? (int) $query['telegram_user_id'] : null;
        $chatId = isset($query['chat_id']) ? (int) $query['chat_id'] : null;
        $role = isset($query['role']) ? (string) $query['role'] : null;

        return ResponseHelper::json($response, $this->service->resolve($telegramUserId, $chatId, $role));
    }

    public function unlink(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $telegramUserId = isset($body['telegram_user_id']) ? (int) $body['telegram_user_id'] : null;
        $role = isset($body['role']) ? (string) $body['role'] : null;
        $accountId = isset($body['account_id']) ? (int) $body['account_id'] : null;

        return ResponseHelper::json($response, $this->service->unlink($telegramUserId, $role, $accountId));
    }

    public function block(Request $request, Response $response): Response
    {
        return $this->setBlocked($request, $response, true);
    }

    public function unblock(Request $request, Response $response): Response
    {
        return $this->setBlocked($request, $response, false);
    }

    private function setBlocked(Request $request, Response $response, bool $blocked): Response
    {
        $body = $request->getParsedBody() ?? [];
        $telegramUserId = isset($body['telegram_user_id']) ? (int) $body['telegram_user_id'] : null;
        $chatId = isset($body['chat_id']) ? (int) $body['chat_id'] : null;
        $role = isset($body['role']) ? (string) $body['role'] : null;

        return ResponseHelper::json($response, $this->service->setBlocked($blocked, $telegramUserId, $chatId, $role));
    }
}
