<?php

use App\Core\AuthCookie;
use App\Core\CorsMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

final class OpenCorsOriginsTest extends TestCase
{
    public function testAnyHttpOriginIsAllowedForCredentialedCors(): void
    {
        $origin = 'https://client.example:8443';
        $request = (new ServerRequestFactory())->createServerRequest('OPTIONS', '/api/v1/resource')
            ->withHeader('Origin', $origin);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };

        $response = (new CorsMiddleware())->process($request, $handler);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame($origin, $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
        self::assertTrue(AuthCookie::isOriginAllowed('http://any-site.test'));
        self::assertFalse(AuthCookie::isOriginAllowed('null'));
    }
}
