<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Core/AuthCookie.php';

use App\Core\AuthCookie;
use App\Core\Auth;
use App\Modules\Auth\AuthController;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\AuthMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

$_ENV['JWT_COOKIE_NAME'] = 'famo_jwt';
$_ENV['JWT_COOKIE_DOMAIN'] = '.famoacademy.ir';
$_ENV['JWT_COOKIE_SECURE'] = 'true';
$_ENV['JWT_COOKIE_SAMESITE'] = 'Lax';
$_ENV['JWT_TTL'] = '3600';

$request = (new ServerRequestFactory())->createServerRequest('POST', 'https://api.famoacademy.ir/api/v1/auth/login', [
    'HTTP_ORIGIN' => 'https://admin.famoacademy.ir',
]);
$response = AuthCookie::issue(new Response(), $request, 'jwt.test.value');
$setCookie = $response->getHeaderLine('Set-Cookie');

foreach ([
    'famo_jwt=jwt.test.value',
    'Domain=.famoacademy.ir',
    'Path=/',
    'HttpOnly',
    'Secure',
    'SameSite=Lax',
] as $expected) {
    if (strpos($setCookie, $expected) === false) {
        fwrite(STDERR, "Missing cookie attribute: {$expected}\n");
        exit(1);
    }
}

$cookieRequest = $request->withCookieParams(['famo_jwt' => 'jwt.test.value']);
if (AuthCookie::tokenFromRequest($cookieRequest) !== 'jwt.test.value') {
    fwrite(STDERR, "JWT cookie was not read from the request.\n");
    exit(1);
}

$cleared = AuthCookie::clear(new Response(), $request)->getHeaderLine('Set-Cookie');
if (strpos($cleared, 'famo_jwt=') !== 0 || strpos($cleared, 'Max-Age=0') === false || strpos($cleared, 'Domain=.famoacademy.ir') === false) {
    fwrite(STDERR, "JWT cookie was not cleared.\n");
    exit(1);
}

$localRequest = (new ServerRequestFactory())->createServerRequest('POST', 'http://localhost:8080/api/v1/auth/login');
$_ENV['JWT_COOKIE_DOMAIN'] = '';
$_ENV['JWT_COOKIE_SECURE'] = '';
$_ENV['JWT_COOKIE_SAMESITE'] = '';
$localCookie = AuthCookie::issue(new Response(), $localRequest, 'jwt.local')->getHeaderLine('Set-Cookie');
if (strpos($localCookie, 'Domain=') !== false || strpos($localCookie, '; Secure') !== false || strpos($localCookie, 'SameSite=Lax') === false) {
    fwrite(STDERR, "Localhost cookie attributes are invalid: {$localCookie}\n");
    exit(1);
}

$xamppSubdomainRequest = (new ServerRequestFactory())->createServerRequest('POST', 'http://api.localhost:8080/api/v1/auth/login')
    ->withHeader('Origin', 'http://admin.localhost');
$xamppSubdomainCookie = AuthCookie::issue(new Response(), $xamppSubdomainRequest, 'jwt.xampp-subdomain')->getHeaderLine('Set-Cookie');
if (strpos($xamppSubdomainCookie, 'SameSite=Lax') === false || strpos($xamppSubdomainCookie, '; Secure') !== false) {
    fwrite(STDERR, "XAMPP host-only cookie attributes are invalid: {$xamppSubdomainCookie}\n");
    exit(1);
}

$localToProductionApi = $request->withHeader('Origin', 'http://localhost');
$crossSiteCookie = AuthCookie::issue(new Response(), $localToProductionApi, 'jwt.cross-site')->getHeaderLine('Set-Cookie');
if (strpos($crossSiteCookie, 'SameSite=None') === false || strpos($crossSiteCookie, '; Secure') === false) {
    fwrite(STDERR, "Localhost-to-production cookie attributes are invalid: {$crossSiteCookie}\n");
    exit(1);
}

$prodToProdCookie = AuthCookie::issue(new Response(), $request->withHeader('Origin', 'https://admin.famoacademy.ir'), 'jwt.same-site')->getHeaderLine('Set-Cookie');
if (strpos($prodToProdCookie, 'SameSite=Lax') === false) {
    fwrite(STDERR, "Production panel cookie should use SameSite=Lax: {$prodToProdCookie}\n");
    exit(1);
}

$allowedRequest = $request->withHeader('Origin', 'https://admin.famoacademy.ir');
if (!AuthCookie::allowsCookieWrite($allowedRequest)) {
    fwrite(STDERR, "Allowed panel origin was rejected.\n");
    exit(1);
}
$deniedRequest = $request->withHeader('Origin', 'https://evil.example');
if (AuthCookie::allowsCookieWrite($deniedRequest)) {
    fwrite(STDERR, "Unlisted origin was allowed to issue auth cookies.\n");
    exit(1);
}

$allowedCors = $allowedRequest->getHeaderLine('Origin');
if (!in_array($allowedCors, AuthCookie::allowedOrigins(), true)) {
    fwrite(STDERR, "Allowed origin is missing from CORS allowlist.\n");
    exit(1);
}

$testToken = Auth::encode(['id' => 42, 'role' => 'student', 'student_id' => 24]);
$cookieAuthRequest = $request->withCookieParams(['famo_jwt' => $testToken]);
$handler = new class implements RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write((string) $request->getAttribute('user')->sub);
        return $response;
    }
};
$authResponse = (new AuthMiddleware())->process($cookieAuthRequest, $handler);
if ($authResponse->getStatusCode() !== 200 || (string) $authResponse->getBody() !== '42') {
    fwrite(STDERR, "Auth middleware did not authenticate the HttpOnly-cookie token.\n");
    exit(1);
}

$authService = new class extends AuthService {
    public function __construct()
    {
    }

    public function login(string $username, string $password): array
    {
        return ['token' => 'jwt.controller.test', 'user' => ['id' => 42, 'role' => 'student']];
    }
};
$loginRequest = $request
    ->withHeader('X-Auth-Mode', 'cookie')
    ->withParsedBody(['username' => 'student', 'password' => 'secret']);
$loginResponse = (new AuthController($authService))->login($loginRequest, new Response());
$loginJson = json_decode((string) $loginResponse->getBody(), true);
if (isset($loginJson['data']['token']) || strpos($loginResponse->getHeaderLine('Set-Cookie'), 'jwt.controller.test') === false) {
    fwrite(STDERR, "Cookie-mode login leaked the token or failed to issue its cookie.\n");
    exit(1);
}

echo "Auth cookie issue/read/clear checks passed.\n";
