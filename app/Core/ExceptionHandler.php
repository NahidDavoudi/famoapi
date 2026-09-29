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
        $errorCode = 'INTERNAL_ERROR';

        $databaseException = $this->findDatabaseException($exception);
        if ($databaseException !== null) {
            $sqlState = (string) $databaseException->getCode();
            $statusCode = str_starts_with($sqlState, '23') ? 409 : 500;
            $message = $statusCode === 409 ? 'داده تکراری یا ناسازگار است' : 'خطای داخلی سرور';
            $errorCode = $statusCode === 409 ? 'CONFLICT' : 'INTERNAL_ERROR';
        } elseif ($exception instanceof ApiException) {
            $statusCode = $exception->getHttpStatus();
            $message = $exception->getMessage();
            $errorCode = $exception->getErrorCode();
        } elseif (is_int($exception->getCode()) && $exception->getCode() >= 400 && $exception->getCode() < 500) {
            $statusCode = $exception->getCode();
            $message = $exception->getMessage();
            $errorCode = match ($statusCode) {
                400, 422 => 'VALIDATION_ERROR',
                401 => 'UNAUTHORIZED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                409 => 'CONFLICT',
                default => 'REQUEST_ERROR',
            };
        }

        if ($logErrors) {
            $requestId = (string) ($request->getAttribute('request_id') ?? bin2hex(random_bytes(8)));
            $logMessage = '[' . date('Y-m-d H:i:s') . '] request_id=' . $requestId . ' '
                . get_class($exception) . ': ' . $exception->getMessage()
                . ' in ' . $exception->getFile() . ':' . $exception->getLine();
            $logPath = __DIR__ . '/../../storage/logs/app.log';
            $logDirectory = dirname($logPath);
            if (!is_dir($logDirectory) && !@mkdir($logDirectory, 0750, true) && !is_dir($logDirectory)) {
                error_log($logMessage);
            } elseif (@file_put_contents($logPath, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
                error_log($logMessage);
            }
        }

        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'success'    => false,
            'data'       => null,
            'pagination' => null,
            'error'      => [
                'code'    => $errorCode,
                'message' => $message,
            ],
        ], JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function findDatabaseException(\Throwable $exception): ?\PDOException
    {
        $current = $exception;
        $seen = [];
        while ($current !== null && !isset($seen[spl_object_id($current)])) {
            $seen[spl_object_id($current)] = true;
            if ($current instanceof \PDOException) {
                return $current;
            }
            $current = $current->getPrevious();
        }

        return null;
    }
}
