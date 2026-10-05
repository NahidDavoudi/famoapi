<?php

namespace App\Modules\Threads;

use App\Core\ApiException;

use App\Core\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Module 3: supporter-facing bot endpoints (inbox, student list, unread,
 * reply). Service-key protected and acting on behalf of the linked supporter.
 */
class SupporterThreadController
{
    public function __construct(private ThreadService $service)
    {
    }

    public function inbox(Request $request, Response $response): Response
    {
        $actor = $request->getAttribute('bot_actor');
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($query['perPage'] ?? 20)));

        return ResponseHelper::json($response, $this->service->inbox($actor, $page, $perPage));
    }

    public function students(Request $request, Response $response): Response
    {
        $actor = $request->getAttribute('bot_actor');

        return ResponseHelper::json($response, $this->service->students($actor));
    }

    public function unread(Request $request, Response $response, array $args): Response
    {
        $actor = $request->getAttribute('bot_actor');
        $query = $request->getQueryParams();
        $limit = max(1, min(500, (int) ($query['limit'] ?? 200)));

        $studentId = (int) ($args['studentId'] ?? 0);
        if ($studentId <= 0) {
            throw new ApiException('شناسه دانش‌آموز الزامی است', 422, 'VALIDATION_ERROR');
        }

        return ResponseHelper::json($response, $this->service->unreadForStudent($actor, $studentId, $limit));
    }

    public function reply(Request $request, Response $response): Response
    {
        $actor = $request->getAttribute('bot_actor');
        $body = $request->getParsedBody() ?? [];

        if (!isset($body['student_id'])) {
            throw new ApiException('شناسه دانش‌آموز الزامی است', 422, 'VALIDATION_ERROR');
        }

        return ResponseHelper::json($response, $this->service->reply($actor, $body), null, 201);
    }
}
