<?php

namespace App\Modules\WeeklyPlans;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PlanController
{
    public function __construct(private PlanService $service) {}

    private function ok(Response $response, mixed $data = null, int $status = 200): Response
    {
        $response->getBody()->write(json_encode([
            'success' => true, 'data' => $data, 'pagination' => null, 'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function fail(Response $response, \RuntimeException $e): Response
    {
        $status = (int) $e->getCode();
        if ($status < 400 || $status > 599) {
            $status = 400;
        }
        $response->getBody()->write(json_encode([
            'success' => false, 'data' => null, 'pagination' => null,
            'error' => ['code' => 'PLAN_ERROR', 'message' => $e->getMessage()],
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /** GET /plans?student_id=X — list a student's plans */
    public function get(Request $request, Response $response): Response
    {
        $studentId = (int) ($request->getQueryParams()['student_id'] ?? 0);
        try {
            return $this->ok($response, $this->service->listForStudent($studentId));
        } catch (\RuntimeException $e) {
            return $this->fail($response, $e);
        }
    }

    /** GET /plans/{id} — full plan with events and times */
    public function getOne(Request $request, Response $response, array $args): Response
    {
        try {
            return $this->ok($response, $this->service->getPlan((int) $args['id']));
        } catch (\RuntimeException $e) {
            return $this->fail($response, $e);
        }
    }

    /** POST /plans — create a full plan */
    public function save(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        try {
            return $this->ok($response, $this->service->saveFull($body), 201);
        } catch (\RuntimeException $e) {
            return $this->fail($response, $e);
        }
    }

    /** PUT /plans/{id} — update an existing plan */
    public function update(Request $request, Response $response, array $args): Response
    {
        $body = $request->getParsedBody() ?? [];
        $body['plan_id'] = (int) $args['id'];
        try {
            return $this->ok($response, $this->service->saveFull($body));
        } catch (\RuntimeException $e) {
            return $this->fail($response, $e);
        }
    }

    /** DELETE /plans/{id} — delete a single plan */
    public function deleteOne(Request $request, Response $response, array $args): Response
    {
        try {
            $this->service->deletePlan((int) $args['id']);
            return $this->ok($response);
        } catch (\RuntimeException $e) {
            return $this->fail($response, $e);
        }
    }

    /** DELETE /plans?student_id=X — clear every plan of a student */
    public function clear(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $body = $request->getParsedBody() ?? [];
        $studentId = (int) ($params['student_id'] ?? $body['student_id'] ?? 0);

        try {
            $deleted = $this->service->clearForStudent($studentId);
            return $this->ok($response, ['deleted' => $deleted, 'student_id' => $studentId]);
        } catch (\RuntimeException $e) {
            return $this->fail($response, $e);
        }
    }

    public function getTemplates(Request $request, Response $response): Response
    {
        return $this->ok($response, $this->service->getTemplates());
    }

    public function getTemplate(Request $request, Response $response, array $args): Response
    {
        $template = $this->service->getTemplate((int) $args['id']);
        if (!$template) {
            return $this->fail($response, new \RuntimeException('قالب یافت نشد', 404));
        }
        return $this->ok($response, $template);
    }

    public function saveTemplate(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        try {
            return $this->ok($response, $this->service->saveTemplate($body), 201);
        } catch (\RuntimeException $e) {
            return $this->fail($response, $e);
        }
    }

    public function deleteTemplate(Request $request, Response $response, array $args): Response
    {
        $this->service->deleteTemplate((int) $args['id']);
        return $this->ok($response);
    }

    public function applyTemplate(Request $request, Response $response, array $args): Response
    {
        $body = $request->getParsedBody() ?? [];
        $studentId = (int) ($body['student_id'] ?? 0);
        try {
            return $this->ok($response, $this->service->applyTemplate((int) $args['id'], $studentId));
        } catch (\RuntimeException $e) {
            return $this->fail($response, $e);
        }
    }
}
