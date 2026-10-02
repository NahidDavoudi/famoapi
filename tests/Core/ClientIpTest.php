<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\ClientIp;
use Psr\Http\Message\ServerRequestInterface;
use Tests\TestCase;

class ClientIpTest extends TestCase
{
    private array $originalServer;
    private array $originalEnv;

    protected function createRequest(
        string $method,
        string $uri,
        array $body = [],
        array $headers = [],
        array $cookies = [],
        array $queryParams = []
    ): ServerRequestInterface {
        $request = $this->requestFactory->createServerRequest($method, $uri, $_SERVER);

        if (!empty($body)) {
            $request = $request->withParsedBody($body);
        }

        if (!empty($headers)) {
            foreach ($headers as $key => $value) {
                $request = $request->withHeader($key, $value);
            }
        }

        if (!empty($cookies)) {
            $request = $request->withCookieParams($cookies);
        }

        if (!empty($queryParams)) {
            $request = $request->withQueryParams($queryParams);
        }

        return $request;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
        $this->originalEnv = $_ENV;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_ENV = $this->originalEnv;
        parent::tearDown();
    }

    public function testUsesRemoteAddrByDefault(): void
    {
        $_ENV['TRUSTED_PROXIES'] = '';
        $_ENV['TRUSTED_PROXY_HEADER'] = '';
        $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
        $request = $this->createRequest('GET', '/x')->withAddedHeader('X-Forwarded-For', '9.9.9.9');
        $this->assertSame('198.51.100.9', ClientIp::fromRequest($request));
    }

    public function testTrustedProxyHeaderUsedOnlyFromTrustedProxy(): void
    {
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.0/8';
        $_ENV['TRUSTED_PROXY_HEADER'] = 'X-Forwarded-For';

        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
        $trusted = $this->createRequest('GET', '/x')->withAddedHeader('X-Forwarded-For', '203.0.113.7, 10.0.0.5');
        $this->assertSame('203.0.113.7', ClientIp::fromRequest($trusted));

        $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
        $untrusted = $this->createRequest('GET', '/x')->withAddedHeader('X-Forwarded-For', '203.0.113.7');
        $this->assertSame('198.51.100.9', ClientIp::fromRequest($untrusted));
    }

    public function testInvalidAddressFailsOpen(): void
    {
        $_SERVER['REMOTE_ADDR'] = 'not-an-ip';
        $_ENV['TRUSTED_PROXIES'] = '';
        $_ENV['TRUSTED_PROXY_HEADER'] = '';
        $this->assertNull(ClientIp::fromRequest($this->createRequest('GET', '/x')));
    }
}
