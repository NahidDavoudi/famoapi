<?php

namespace App\Modules\Auth;

use App\Core\Auth;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorized('توکن ارسال نشده است');
        }

        $token = substr($authHeader, 7);

        try {
            $decoded = Auth::decode($token);
        } catch (\Exception $e) {
            return $this->unauthorized('توکن نامعتبر یا منقضی شده است');
        }

        $request = $request->withAttribute('user', $decoded);

        return $handler->handle($request);
    }

    private function unauthorized(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'success'    => false,
            'data'       => null,
            'pagination' => null,
            'error'      => [
                'code'    => 'UNAUTHORIZED',
                'message' => $message,
            ],
        ], JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}