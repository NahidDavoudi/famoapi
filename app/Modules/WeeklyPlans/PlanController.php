<?php

namespace App\Modules\WeeklyPlans;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PlanController
{
    public function __construct(private PlanService $service) {}

    public function get(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $studentId = (int) ($params['student_id'] ?? 0);
        $result = $this->service->get($studentId);

        $response->getBody()->write(json_encode([
            'success' => true, 'data' => $result, 'pagination' => null, 'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function save(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $studentId = (int) ($body['student_id'] ?? 0);
        $items = $body['items'] ?? [];

        try {
            $result = $this->service->save($studentId, $items);
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false, 'data' => null, 'pagination' => null,
                'error' => ['code' => 'ERROR', 'message' => $e->getMessage()],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getCode() ?: 400);
        }

        $response->getBody()->write(json_encode([
            'success' => true, 'data' => $result, 'pagination' => null, 'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function clear(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $studentId = (int) ($body['student_id'] ?? 0);

        try {
            $this->service->clear($studentId);
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false, 'data' => null, 'pagination' => null,
                'error' => ['code' => 'ERROR', 'message' => $e->getMessage()],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getCode() ?: 400);
        }

        $response->getBody()->write(json_encode([
            'success' => true, 'data' => null, 'pagination' => null, 'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function getTemplates(Request $request, Response $response): Response
    {
        $result = $this->service->getTemplates();
        $response->getBody()->write(json_encode([
            'success' => true, 'data' => $result, 'pagination' => null, 'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function getTemplate(Request $request, Response $response, array $args): Response
    {
        $result = $this->service->getTemplate((int) $args['id']);
        $response->getBody()->write(json_encode([
            'success' => true, 'data' => $result, 'pagination' => null, 'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function saveTemplate(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        try {
            $result = $this->service->saveTemplate($body);
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false, 'data' => null, 'pagination' => null,
                'error' => ['code' => 'ERROR', 'message' => $e->getMessage()],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getCode() ?: 400);
        }
        $response->getBody()->write(json_encode([
            'success' => true, 'data' => $result, 'pagination' => null, 'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function deleteTemplate(Request $request, Response $response, array $args): Response
    {
        $this->service->deleteTemplate((int) $args['id']);
        $response->getBody()->write(json_encode([
            'success' => true, 'data' => null, 'pagination' => null, 'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function applyTemplate(Request $request, Response $response, array $args): Response
    {
        $body = $request->getParsedBody() ?? [];
        $studentId = (int) ($body['student_id'] ?? 0);
        try {
            $result = $this->service->applyTemplate((int) $args['id'], $studentId);
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false, 'data' => null, 'pagination' => null,
                'error' => ['code' => 'ERROR', 'message' => $e->getMessage()],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getCode() ?: 400);
        }
        $response->getBody()->write(json_encode([
            'success' => true, 'data' => $result, 'pagination' => null, 'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }
}