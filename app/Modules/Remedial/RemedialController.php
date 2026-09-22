<?php

namespace App\Modules\Remedial;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class RemedialController
{
    public function __construct(private RemedialService $service)
    {
    }

    public function getSessions(Request $request, Response $response): Response
    {
        $result = $this->service->getSessions();

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $result,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function createSession(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];

        try {
            $result = $this->service->createSession([
                'title'      => $body['title'],
                'start_date' => $body['start_date'],
                'end_date'   => $body['end_date'],
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

    public function getSessionData(Request $request, Response $response, array $args): Response
    {
        try {
            $result = $this->service->getSessionData((int) $args['id']);
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

    public function toggleAttendance(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];

        $result = $this->service->toggleAttendance(
            (int) $body['session_id'],
            (int) $body['class_id'],
            (int) $body['student_id']
        );

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $result,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function createClass(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];

        try {
            $result = $this->service->createClass([
                'session_id'  => $body['session_id'],
                'field'       => $body['field'],
                'class_name'  => $body['class_name'],
                'description' => $body['description'] ?? '',
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

    public function updateStudentTime(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];

        $this->service->updateStudentTime(
            (int) $body['session_id'],
            (int) $body['student_id'],
            $body['field'],
            $body['value']
        );

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => null,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function deleteClass(Request $request, Response $response, array $args): Response
    {
        $this->service->deleteClass((int) $args['id']);

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => null,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}