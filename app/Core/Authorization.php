<?php

namespace App\Core;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class Authorization implements MiddlewareInterface
{
    private string $requiredRole;

    public function __construct(string $requiredRole)
    {
        $this->requiredRole = $requiredRole;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $user = $request->getAttribute('user');

        if (!$user) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode([
                'success' => false,
                'data' => null,
                'pagination' => null,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'دسترسی غیرمجاز',
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response
                ->withStatus(403)
                ->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $userRole = $user->role ?? '';

        if ($this->requiredRole === 'admin' && $userRole !== 'admin') {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode([
                'success' => false,
                'data' => null,
                'pagination' => null,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'دسترسی محدود به مدیران',
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response
                ->withStatus(403)
                ->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        if ($this->requiredRole === 'supporter' && !in_array($userRole, ['admin', 'supporter'])) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode([
                'success' => false,
                'data' => null,
                'pagination' => null,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'دسترسی محدود به پشتیبانان',
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response
                ->withStatus(403)
                ->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        return $handler->handle($request);
    }
}