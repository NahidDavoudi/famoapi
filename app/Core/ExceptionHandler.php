<?php

namespace App\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class ExceptionHandler
{
    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        $statusCode = 500;
        $message = 'خطای داخلی سرور';

        $code = $exception->getCode();
        if (is_int($code) && $code >= 400 && $code < 500) {
            $statusCode = $code;
            $message = $exception->getMessage();
        }

        if ($logErrors) {
            $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $exception->getMessage()
                . ' in ' . $exception->getFile() . ':' . $exception->getLine();
            error_log($logMessage . PHP_EOL, 3, __DIR__ . '/../../storage/logs/app.log');
        }

        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'success'    => false,
            'data'       => null,
            'pagination' => null,
            'error'      => [
                'code'    => $statusCode === 500 ? 'INTERNAL_ERROR' : 'REQUEST_ERROR',
                'message' => $message,
            ],
        ], JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}