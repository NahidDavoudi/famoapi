<?php

namespace App\Core;

use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;

class ResponseHelper
{
    public static function json(Response $response, mixed $data, ?array $pagination = null, int $status = 200): Response
    {
        $body = json_encode([
            'success' => true,
            'data'    => $data,
            'pagination' => $pagination,
            'error'   => null,
        ], JSON_UNESCAPED_UNICODE);
        
        $response->getBody()->write($body);
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
    
    public static function error(Response $response, string $code, string $message, int $status = 400): Response
    {
        $body = json_encode([
            'success' => false,
            'data'    => null,
            'pagination' => null,
            'error'   => ['code' => $code, 'message' => $message],
        ], JSON_UNESCAPED_UNICODE);
        
        $response->getBody()->write($body);
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
    
    public static function paginated(Response $response, mixed $data, array $pagination): Response
    {
        return self::json($response, $data, $pagination);
    }
}