<?php

use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

\App\Core\Eloquent::boot();

date_default_timezone_set('UTC');

$app = AppFactory::create();

// Normalize request bodies and support POST + method override for multipart updates.
// PHP/Slim do not populate multipart fields for a native PUT/PATCH request.
$app->add(function (ServerRequestInterface $request, $handler) {
    $method = strtoupper($request->getMethod());
    $contentTypeHeader = $request->getHeaderLine('Content-Type');
    $contentType = strtolower($contentTypeHeader);
    $parsedBody = $request->getParsedBody();

    if ($parsedBody === null && !empty($_POST)) {
        $parsedBody = $_POST;
        $request = $request->withParsedBody($parsedBody);
    } elseif ($parsedBody === null
        && in_array($method, ['PUT', 'PATCH'], true)
        && str_contains($contentType, 'application/x-www-form-urlencoded')) {
        parse_str((string) $request->getBody(), $parsedBody);
        $request = $request->withParsedBody($parsedBody);
    }

    if ($method === 'POST') {
        $override = $request->getHeaderLine('X-HTTP-Method-Override');
        if (!$override && is_array($parsedBody)) {
            $override = (string) ($parsedBody['_method'] ?? '');
        }
        $override = strtoupper($override);
        if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
            $request = $request->withMethod($override);
        }
    } elseif (in_array($method, ['PUT', 'PATCH'], true)
        && str_contains($contentType, 'multipart/form-data')) {
        $request = App\Core\MultipartFormDataParser::applyToRequest($request);
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
