<?php

namespace App\Modules\Outbox;

use App\Core\ApiException;
use App\Core\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Module 4: outbox pull/report endpoints for the bot host. Service-key
 * protected (the bot worker is a trusted transport, not an acting user).
 */
class OutboxController
{
    public function __construct(private OutboxService $service)
    {
    }

    public function claim(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $limit = (int) ($body['limit'] ?? 10);
        $workerId = isset($body['worker_id']) ? (string) $body['worker_id'] : null;

        return ResponseHelper::json($response, $this->service->claim($limit, $workerId));
    }

    public function report(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $workerId = (string) ($body['worker_id'] ?? '');
        $results = $body['results'] ?? null;

        if ($workerId === '') {
            throw new ApiException('شناسه کارگر الزامی است', 422, 'VALIDATION_ERROR');
        }
        if (!is_array($results)) {
            throw new ApiException('فهرست نتایج نامعتبر است', 422, 'VALIDATION_ERROR');
        }

        return ResponseHelper::json($response, $this->service->report($workerId, $results));
    }
}
