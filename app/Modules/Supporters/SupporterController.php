<?php

namespace App\Modules\Supporters;

use App\Core\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SupporterController
{
    private array $validGrades = [7, 8, 9, 10, 11, 12];
    private array $validFields = ['ریاضی', 'تجربی', 'انسانی', 'زبان', 'هنر', 'عمومی', 'راهنمایی'];

    public function __construct(private SupporterService $service)
    {
    }

    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['perPage'] ?? 20);

        $result = $this->service->list($page, $perPage);

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $result['items'],
            'pagination' => $result['pagination'],
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];

        $validator = new Validator();
        $validator
            ->required('name', 'نام و نام خانوادگی', $body['name'] ?? null)
            ->required('grade', 'پایه تحصیلی', $body['grade'] ?? null)
            ->inArray('grade', 'پایه تحصیلی', $body['grade'] ?? null, $this->validGrades)
            ->required('field', 'رشته تحصیلی', $body['field'] ?? null)
            ->inArray('field', 'رشته تحصیلی', $body['field'] ?? null, $this->validFields)
            ->required('phone', 'شماره تلفن', $body['phone'] ?? null)
            ->phone('phone', 'شماره تلفن', $body['phone'] ?? null);

        if (!$validator->passes()) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => $validator->firstError(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        try {
            $result = $this->service->create([
                'name'    => $body['name'],
                'grade'   => $body['grade'],
                'field'   => $body['field'],
                'chat_id' => $body['chat_id'] ?? null,
            ]);
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => 'CREATION_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getCode() ?: 500)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $result,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        try {
            $result = $this->service->get((int) $args['id']);
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => 'NOT_FOUND',
                    'message' => $e->getMessage(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getCode() ?: 404)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $result,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $body = $request->getParsedBody() ?? [];

        $data = [];
        if (isset($body['name'])) $data['name'] = $body['name'];
        if (isset($body['grade'])) $data['grade'] = $body['grade'];
        if (isset($body['field'])) $data['field'] = $body['field'];
        if (isset($body['chat_id'])) $data['chat_id'] = $body['chat_id'];
        if (isset($body['password'])) $data['password'] = $body['password'];

        try {
            $result = $this->service->update((int) $args['id'], $data);
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => 'UPDATE_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getCode() ?: 500)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $result,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $this->service->delete((int) $args['id']);
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => 'DELETE_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getCode() ?: 500)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => null,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}