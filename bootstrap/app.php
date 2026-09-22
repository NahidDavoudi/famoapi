<?php

use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$app = AppFactory::create();

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
    $origins = explode(',', $_ENV['CORS_ORIGINS'] ?? '*');
    $origin = $request->getHeaderLine('Origin');

    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', $origin ?: '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

$app->options('/{routes:.+}', function (ServerRequestInterface $request, Response $response) {
    return $response->withStatus(200);
});

$app->add(function (ServerRequestInterface $request, $handler) {
    $response = $handler->handle($request);
    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
});

$routes = require __DIR__ . '/../routes/api.php';
$routes($app);

return $app;