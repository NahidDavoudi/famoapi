<?php

namespace App\Modules\Public;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PublicController
{
    private PublicService $service;

    public function __construct()
    {
        $this->service = new PublicService();
    }

    public function getCourses(Request $request, Response $response): Response
    {
        $data = $this->service->getCourses();
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $data,
            'pagination' => null,
            'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function getInstructors(Request $request, Response $response): Response
    {
        $data = $this->service->getInstructors();
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $data,
            'pagination' => null,
            'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function getSupporters(Request $request, Response $response): Response
    {
        $data = $this->service->getSupporters();
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $data,
            'pagination' => null,
            'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }
}