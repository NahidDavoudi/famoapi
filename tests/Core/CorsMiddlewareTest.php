<?php

use App\Core\CorsMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

final class CorsMiddlewareTest extends TestCase
{
    private array $originalCorsOrigins;
    private bool $hadCorsOrigins;

    protected function setUp(): void
    {
        $this->hadCorsOrigins = array_key_exists('CORS_ORIGINS', $_ENV);
        $this->originalCorsOrigins = $_ENV['CORS_ORIGINS'] ?? [];
        unset($_ENV['CORS_ORIGINS']);
    }

    protected function tearDown(): void
    {
        if ($this->hadCorsOrigins) {
            $_ENV['CORS_ORIGINS'] = $this->originalCorsOrigins;
        } else {
            unset($_ENV['CORS_ORIGINS']);
        }
        parent::tearDown();
    }

    public function testSubdomainPreflightReceivesCorsHeaders(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('OPTIONS', '/api/v1/instructors/1')
            ->withHeader('Origin', 'https://new-panel.famoacademy.ir');

        $response = (new CorsMiddleware())->process($request, $this->handler());

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('https://new-panel.famoacademy.ir', $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
        self::assertSame('86400', $response->getHeaderLine('Access-Control-Max-Age'));
    }

    public function testAllowedSubdomainIsAcceptedForCookieWrites(): void
    {
        $called = false;
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/api/v1/instructors/1')
            ->withHeader('Origin', 'https://new-panel.famoacademy.ir')
            ->withCookieParams(['famo_jwt' => 'token']);

        $response = (new CorsMiddleware())->process($request, $this->handler($called, 200));

        self::assertTrue($called);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('https://new-panel.famoacademy.ir', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testDisallowedCookieWriteIsRejectedWithoutGrantingCorsAccess(): void
    {
        $called = false;
        $request = (new ServerRequestFactory())->createServerRequest('PUT', '/api/v1/instructors/1')
            ->withHeader('Origin', 'https://attacker.example')
            ->withCookieParams(['famo_jwt' => 'token']);

        $response = (new CorsMiddleware())->process($request, $this->handler($called));

        self::assertFalse($called);
        self::assertSame(403, $response->getStatusCode());
        self::assertSame('CSRF_ORIGIN_REJECTED', json_decode((string) $response->getBody(), true)['error']['code']);
        self::assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testHandledErrorResponseKeepsCorsHeaders(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/v1/instructors')
            ->withHeader('Origin', 'https://new-panel.famoacademy.ir');

        $response = (new CorsMiddleware())->process($request, $this->handler(status: 500));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('https://new-panel.famoacademy.ir', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testUntrustedSuffixAndInsecureSubdomainsAreNotAllowed(): void
    {
        self::assertFalse(\App\Core\AuthCookie::isOriginAllowed('https://famoacademy.ir.attacker.example'));
        self::assertFalse(\App\Core\AuthCookie::isOriginAllowed('http://new-panel.famoacademy.ir'));
    }

    private function handler(?bool &$called = null, int $status = 200): RequestHandlerInterface
    {
        return new class($called, $status) implements RequestHandlerInterface {
            public function __construct(private ?bool &$called, private int $status)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                if ($this->called !== null) {
                    $this->called = true;
                }
                return (new Response())->withStatus($this->status);
            }
        };
    }
}
