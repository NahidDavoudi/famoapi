<?php
declare(strict_types=1);

namespace App\Core;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;
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
        $retryAfter = null;

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
            if ($exception instanceof RateLimitedException) {
                $retryAfter = $exception->getRetryAfter();
            }
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

            $context = [
                'request_id' => $requestId,
                'class'      => get_class($exception),
                'file'       => $exception->getFile(),
                'line'       => $exception->getLine(),
            ];

            if ($logErrorDetails) {
                $context['trace'] = $exception->getTraceAsString();
            }

            // خطاهای PDO رو با جزئیات SQL STATE هم لاگ کن
            if ($databaseException !== null) {
                $context['sql_state'] = (string) $databaseException->getCode();
            }

            Logger::error($exception->getMessage(), $context);
        }

        $response = new Response();
        $response->getBody()->write(json_encode([
            'success'    => false,
            'data'       => null,
            'pagination' => null,
            'error'      => [
                'code'    => $errorCode,
                'message' => $message,
            ],
        ], JSON_UNESCAPED_UNICODE));

        $response = $response
            ->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');

        if ($retryAfter !== null) {
            $response = $response->withHeader('Retry-After', (string) $retryAfter);
        }

        return $response;
    }

    private function findDatabaseException(Throwable $exception): ?\PDOException
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