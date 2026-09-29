<?php
declare(strict_types=1);

namespace App\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PublicCacheMiddleware implements MiddlewareInterface
{
    public function __construct(
        private int $maxAge = 300,
        private string $visibility = 'public'
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (
            $request->getMethod() !== 'GET'
            || $response->getStatusCode() !== 200
            || $response->hasHeader('Cache-Control')
        ) {
            return $response;
        }

        return $response
            ->withHeader('Cache-Control', "{$this->visibility}, max-age={$this->maxAge}")
            ->withAddedHeader('Vary', 'Origin');
    }
}
