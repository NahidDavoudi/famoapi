<?php

namespace Tests\Core;

use App\Core\ApiException;
use App\Core\ExceptionHandler;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class ApiExceptionTest extends TestCase
{
    public function testDomainExceptionUsesItsSafeStatusAndCode(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/test');
        $response = (new ExceptionHandler())($request, new ApiException('Missing item', 404, 'NOT_FOUND'), false, false, false);
        $body = json_decode((string) $response->getBody(), true);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('NOT_FOUND', $body['error']['code']);
        self::assertSame('Missing item', $body['error']['message']);
    }

    public function testDatabaseExceptionDoesNotExposeDriverMessage(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/test');
        $exception = new \PDOException('SQLSTATE[23000]: secret table detail', 23000);
        $response = (new ExceptionHandler())($request, $exception, false, false, false);
        $body = json_decode((string) $response->getBody(), true);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('CONFLICT', $body['error']['code']);
        self::assertSame('داده تکراری یا ناسازگار است', $body['error']['message']);
        self::assertStringNotContainsString('secret table detail', (string) $response->getBody());
    }

    public function testWrappedDatabaseExceptionIsStillTreatedAsInfrastructureFailure(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/test');
        $exception = new \RuntimeException('database failed', 500, new \PDOException('private SQL', 2002));
        $response = (new ExceptionHandler())($request, $exception, false, false, false);
        $body = json_decode((string) $response->getBody(), true);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('INTERNAL_ERROR', $body['error']['code']);
        self::assertSame('خطای داخلی سرور', $body['error']['message']);
        self::assertStringNotContainsString('database failed', (string) $response->getBody());
    }
}
