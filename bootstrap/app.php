<?php

use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

date_default_timezone_set('UTC');

$app = AppFactory::create();

// Ensure multipart/form-data fields are available via getParsedBody().
// Slim's BodyParsingMiddleware does not parse multipart, so merge $_POST.
$app->add(function (ServerRequestInterface $request, $handler) {
    if ($request->getParsedBody() === null && !empty($_POST)) {
        $request = $request->withParsedBody($_POST);
    }
    return $handler->handle($request);
});

$app->addBodyParsingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(
    filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    true,
    true
);

$errorMiddleware->setErrorHandler(HttpNotFoundException::class, function (
    ServerRequestInterface $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) {
    $response = new Response();
    $response->getBody()->write(json_encode([
        'success'    => false,
        'data'       => null,
        'pagination' => null,
        'error'      => [
            'code'    => 'NOT_FOUND',
            'message' => 'مسیر مورد نظر یافت نشد',
        ],
    ], JSON_UNESCAPED_UNICODE));

    return $response
        ->withStatus(404)
        ->withHeader('Content-Type', 'application/json; charset=utf-8');
});

$errorMiddleware->setErrorHandler(HttpMethodNotAllowedException::class, function (
    ServerRequestInterface $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) {
    $response = new Response();
    $response->getBody()->write(json_encode([
        'success'    => false,
        'data'       => null,
        'pagination' => null,
        'error'      => [
            'code'    => 'METHOD_NOT_ALLOWED',
            'message' => 'متود درخواستی مجاز نیست',
        ],
    ], JSON_UNESCAPED_UNICODE));

    return $response
        ->withStatus(405)
        ->withHeader('Content-Type', 'application/json; charset=utf-8');
});

$errorMiddleware->setDefaultErrorHandler(new App\Core\ExceptionHandler());

$app->add(function (ServerRequestInterface $request, $handler) {
    $origin = rtrim($request->getHeaderLine('Origin'), '/');
    $configuredOrigins = \App\Core\AuthCookie::allowedOrigins();

    $originAllowed = $origin !== '' && in_array($origin, $configuredOrigins, true);
    $method = strtoupper($request->getMethod());
    $cookieRequest = \App\Core\AuthCookie::isPresent($request);

    // Handle CORS preflight (OPTIONS) directly in middleware
    if ($method === 'OPTIONS') {
        if ($origin !== '' && !$originAllowed) {
            return (new Response())->withStatus(403);
        }

        // For allowed origins, return proper preflight response with CORS headers
        if ($originAllowed) {
            return (new Response())
                ->withStatus(204)
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Vary', 'Origin')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, Origin, X-Requested-With, X-Auth-Mode')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        // No origin header (non-browser request) - allow but without CORS headers
        return (new Response())->withStatus(204);
    }

    // Cookie-authenticated writes are accepted only from an explicitly allowed panel origin.
    $cookieAuthRoute = str_starts_with($request->getUri()->getPath(), '/api/v1/auth/');
    if (
        $cookieAuthRoute
        && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
        && !\App\Core\AuthCookie::allowsCookieWrite($request)
    ) {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'data' => null,
            'pagination' => null,
            'error' => ['code' => 'CSRF_ORIGIN_REJECTED', 'message' => 'مبدأ درخواست مجاز نیست'],
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    if ($cookieRequest && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !$originAllowed) {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'data' => null,
            'pagination' => null,
            'error' => ['code' => 'CSRF_ORIGIN_REJECTED', 'message' => 'مبدأ درخواست مجاز نیست'],
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    $response = $handler->handle($request);

    if ($originAllowed) {
        $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
        $response = $response->withHeader('Vary', 'Origin');
    }

    return $response
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, Origin, X-Requested-With, X-Auth-Mode')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

$app->add(function (ServerRequestInterface $request, $handler) {
    $response = $handler->handle($request);
    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
});

$routes = require __DIR__ . '/../routes/api.php';
$routes($app);

return $app;
