<?php

namespace App\Core;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class Authorization implements MiddlewareInterface
{
    private const ROLE_RULES = [
        'admin' => [
            'roles'   => ['admin'],
            'message' => 'دسترسی محدود به مدیران',
        ],
        'supporter' => [
            'roles'   => ['admin', 'supporter'],
            'message' => 'دسترسی محدود به پشتیبانان',
        ],
        'teacher' => [
            'roles'   => ['teacher'],
            'message' => 'دسترسی محدود به مدرسان',
        ],
    ];

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

        $rule = self::ROLE_RULES[$this->requiredRole] ?? null;

        if ($rule !== null && !in_array($user->role ?? '', $rule['roles'], true)) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode([
                'success' => false,
                'data' => null,
                'pagination' => null,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => $rule['message'],
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response
                ->withStatus(403)
                ->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        return $handler->handle($request);
    }
}