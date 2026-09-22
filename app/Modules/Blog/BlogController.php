<?php

namespace App\Modules\Blog;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class BlogController
{
    private BlogService $service;

    public function __construct()
    {
        $this->service = new BlogService();
    }

    public function getPosts(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['per_page'] ?? 20);

        $result = $this->service->getPublishedPosts($page, $perPage);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['posts' => $result['posts']],
            'pagination' => $result['pagination'],
            'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function getPostsByCategory(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['per_page'] ?? 20);
        $category = $args['category'] ?? '';

        $result = $this->service->getPostsByCategory($category, $page, $perPage);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['posts' => $result['posts']],
            'pagination' => $result['pagination'],
            'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function getPost(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'] ?? '';
        $post = $this->service->getPost($slug);

        if (!$post) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'data' => null,
                'pagination' => null,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'پست مورد نظر یافت نشد'],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['post' => $post],
            'pagination' => null,
            'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function getCategories(Request $request, Response $response): Response
    {
        $result = $this->service->getCategories();

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $result,
            'pagination' => null,
            'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function getAllPosts(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['per_page'] ?? 20);

        $result = $this->service->getAllPosts($page, $perPage);

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['posts' => $result['posts']],
            'pagination' => $result['pagination'],
            'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function getPostById(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $post = $this->service->getPostById($id);

        if (!$post) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'data' => null,
                'pagination' => null,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'پست مورد نظر یافت نشد'],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => ['post' => $post],
            'pagination' => null,
            'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function createPost(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        try {
            $result = $this->service->createPost($data);
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $result,
                'pagination' => null,
                'error' => null,
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(201);
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'data' => null,
                'pagination' => null,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400);
        }
    }

    public function updatePost(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $data = $request->getParsedBody();

        try {
            $result = $this->service->updatePost($id, $data);
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $result,
                'pagination' => null,
                'error' => null,
            ], JSON_UNESCAPED_UNICODE));
            return $response;
        } catch (\RuntimeException $e) {
            $code = $e->getMessage() === 'پست مورد نظر یافت نشد' ? 404 : 400;
            $errorCode = $code === 404 ? 'NOT_FOUND' : 'VALIDATION_ERROR';
            $response->getBody()->write(json_encode([
                'success' => false,
                'data' => null,
                'pagination' => null,
                'error' => ['code' => $errorCode, 'message' => $e->getMessage()],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($code);
        }
    }

    public function deletePost(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);

        try {
            $this->service->deletePost($id);
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => null,
                'pagination' => null,
                'error' => null,
            ], JSON_UNESCAPED_UNICODE));
            return $response;
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'data' => null,
                'pagination' => null,
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(404);
        }
    }
}