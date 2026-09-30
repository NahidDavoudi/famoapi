<?php

namespace App\Modules\Attendance;

use App\Core\ApiException;
use App\Core\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AttendanceController
{
    public function __construct(private AttendanceService $service)
    {
    }

    private function ok(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $data,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function fail(Response $response, ApiException $e): Response
    {
        $response->getBody()->write(json_encode([
            'success'    => false,
            'data'       => null,
            'pagination' => null,
            'error'      => [
                'code'    => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ],
        ], JSON_UNESCAPED_UNICODE));

        return $response->withStatus($e->getHttpStatus())->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        try {
            return $this->ok($response, $this->service->listDay($params['date'] ?? null, $params['field'] ?? null));
        } catch (ApiException $e) {
            return $this->fail($response, $e);
        }
    }

    public function printList(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        try {
            return $this->ok($response, $this->service->printList($params['date'] ?? null, $params['field'] ?? null));
        } catch (ApiException $e) {
            return $this->fail($response, $e);
        }
    }

    public function upsertEntry(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];

        $validator = new Validator();
        $validator
            ->required('date', 'تاریخ', $body['date'] ?? null)
            ->required('student_id', 'شناسه دانش‌آموز', $body['student_id'] ?? null)
            ->inArray('status', 'وضعیت', $body['status'] ?? null, ['present', 'absent']);

        if (!$validator->passes()) {
            return $this->fail($response, new ApiException($validator->firstError(), 422, 'VALIDATION_ERROR'));
        }

        try {
            return $this->ok($response, $this->service->upsertEntry($body));
        } catch (ApiException $e) {
            return $this->fail($response, $e);
        }
    }

    public function setTime(Request $request, Response $response, array $args): Response
    {
        $body = $request->getParsedBody() ?? [];

        $validator = new Validator();
        $validator->required('field', 'ستون', $body['field'] ?? null);

        if (!$validator->passes()) {
            return $this->fail($response, new ApiException($validator->firstError(), 422, 'VALIDATION_ERROR'));
        }

        try {
            return $this->ok($response, $this->service->setTime(
                (int) $args['id'],
                (string) $body['field'],
                $body['value'] ?? null
            ));
        } catch (ApiException $e) {
            return $this->fail($response, $e);
        }
    }

    public function addGuest(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];

        $validator = new Validator();
        $validator
            ->required('date', 'تاریخ', $body['date'] ?? null)
            ->required('guest_name', 'نام مهمان', $body['guest_name'] ?? null)
            ->required('field', 'رشته', $body['field'] ?? null);

        if (!$validator->passes()) {
            return $this->fail($response, new ApiException($validator->firstError(), 422, 'VALIDATION_ERROR'));
        }

        try {
            return $this->ok($response, $this->service->addGuest($body), 201);
        } catch (ApiException $e) {
            return $this->fail($response, $e);
        }
    }
}
