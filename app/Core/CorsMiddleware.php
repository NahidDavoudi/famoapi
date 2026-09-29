<?php

namespace App\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class CorsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = rtrim($request->getHeaderLine('Origin'), '/');
        $originAllowed = AuthCookie::isOriginAllowed($origin);
        $method = strtoupper($request->getMethod());
        $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        $withCors = static function (ResponseInterface $response) use ($origin, $originAllowed): ResponseInterface {
            if ($originAllowed) {
                $response = $response
                    ->withHeader('Access-Control-Allow-Origin', $origin)
                    ->withHeader('Access-Control-Allow-Credentials', 'true')
                    ->withHeader('Vary', 'Origin');
            }

            return $response
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, Origin, X-Requested-With, X-Auth-Mode')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        };

        if ($method === 'OPTIONS') {
            if ($origin !== '' && !$originAllowed) {
                return $withCors((new Response())->withStatus(403));
            }

            return $withCors((new Response())
                ->withStatus(204)
                ->withHeader('Access-Control-Max-Age', '86400'));
        }

        $cookieAuthRoute = str_starts_with($request->getUri()->getPath(), '/api/v1/auth/');
        $cookieRequest = AuthCookie::isPresent($request);
        if ($isWrite && !$originAllowed && ($cookieAuthRoute || $cookieRequest)) {
            $response = new Response();
            $response->getBody()->write(json_encode([
                'success' => false,
                'data' => null,
                'pagination' => null,
                'error' => ['code' => 'CSRF_ORIGIN_REJECTED', 'message' => 'مبدأ درخواست مجاز نیست'],
            ], JSON_UNESCAPED_UNICODE));

            return $withCors($response
                ->withStatus(403)
                ->withHeader('Content-Type', 'application/json; charset=utf-8'));
        }

        return $withCors($handler->handle($request));
    }
}
