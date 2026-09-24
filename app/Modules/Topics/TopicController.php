<?php

namespace App\Modules\Topics;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TopicController
{
    public function __construct(private TopicService $service)
    {
    }

    public function getChildren(Request $request, Response $response, array $args): Response
    {
        $parentId = (int) ($args['parent_id'] ?? $request->getQueryParams()['parent_id'] ?? 0);
        $result = $this->service->getChildren($parentId);

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $result,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function search(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $query = $params['q'] ?? '';
        $result = $this->service->search($query);

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $result,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function getPath(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $result = $this->service->getPath($id);

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $result,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function getSubjectsForGrade(Request $request, Response $response, array $args): Response
    {
        $grade = (int) ($args['grade'] ?? 0);
        $params = $request->getQueryParams();
        $field = $params['field'] ?? '';
        $result = $this->service->getSubjectsForGrade($grade, $field);

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $result,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}