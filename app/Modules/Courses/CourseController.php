<?php

namespace App\Modules\Courses;

use App\Core\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CourseController
{
    public function __construct(private CourseService $service)
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
        $validator->required('name', 'نام دوره', $body['name'] ?? null);

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

        $files = $request->getUploadedFiles();
        $backgroundImage = null;
        if (!empty($files['background_image'])) {
            $backgroundImage = $files['background_image'];
        }

        try {
            $result = $this->service->create([
                'name'                => $body['name'],
                'icon'                => $body['icon'] ?? null,
                'gradient_color_from' => $body['gradient_color_from'] ?? null,
                'gradient_color_to'   => $body['gradient_color_to'] ?? null,
                'background_image_url'=> $body['background_image_url'] ?? ($backgroundImage ?? null),
                'description'         => $body['description'] ?? null,
                'price'               => isset($body['price']) ? (int) $body['price'] : 0,
                'display_order'       => isset($body['display_order']) ? (int) $body['display_order'] : 0,
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
        $files = $request->getUploadedFiles();

        $data = [];
        if (isset($body['name'])) $data['name'] = $body['name'];
        if (isset($body['icon'])) $data['icon'] = $body['icon'];
        if (isset($body['gradient_color_from'])) $data['gradient_color_from'] = $body['gradient_color_from'];
        if (isset($body['gradient_color_to'])) $data['gradient_color_to'] = $body['gradient_color_to'];
        if (isset($body['description'])) $data['description'] = $body['description'];
        if (isset($body['price'])) $data['price'] = (int) $body['price'];
        if (isset($body['display_order'])) $data['display_order'] = (int) $body['display_order'];
        if (!empty($files['background_image_url'])) {
            $data['background_image_url'] = $files['background_image_url'];
        }

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