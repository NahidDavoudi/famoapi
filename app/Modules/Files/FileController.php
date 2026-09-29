<?php

namespace App\Modules\Files;

use App\Core\StudentScope;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FileController
{
    public function __construct(private FileService $service)
    {
    }

    public function list(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $studentId = StudentScope::studentId(
            $request,
            isset($params['student_id']) ? (int) $params['student_id'] : null
        );
        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['perPage'] ?? 20);

        $result = $this->service->list($studentId, $page, $perPage);

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $result['items'],
            'pagination' => $result['pagination'],
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function upload(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        $body = $request->getParsedBody() ?? [];

        $file = $uploadedFiles['file'] ?? null;
        if (!$file instanceof \Psr\Http\Message\UploadedFileInterface
            || $file->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => $e->getErrorCode(),
                    'message' => 'آپلود فایل با خطا مواجه شد',
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $studentId = (int) StudentScope::studentId(
            $request,
            (int) ($body['student_id'] ?? 0),
            true
        );
        if ($studentId <= 0) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'شناسه دانش‌آموز الزامی است',
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $description = $body['description'] ?? null;

        try {
            $result = $this->service->upload($file, $studentId, $description);
        } catch (\App\Core\ApiException $e) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => 'UPLOAD_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getHttpStatus())->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $result,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function download(Request $request, Response $response, array $args): Response
    {
        try {
            $enforceStudentId = StudentScope::isStudent($request) ? StudentScope::selfId($request) : null;
            $download = $this->service->getDownload((int) $args['id'], $enforceStudentId);
        } catch (\App\Core\ApiException $e) {
            return \App\Core\ResponseHelper::error(
                $response,
                $e->getErrorCode(),
                $e->getMessage(),
                $e->getHttpStatus()
            );
        }

        $bytes = file_get_contents($download['path']);
        if ($bytes === false) {
            return \App\Core\ResponseHelper::error($response, 'INTERNAL_ERROR', 'خطای داخلی سرور', 500);
        }
        $filename = basename((string) $download['file']['file_path']);
        $response->getBody()->write($bytes);
        return $response
            ->withHeader('Content-Type', $download['mime_type'])
            ->withHeader('Content-Length', (string) strlen($bytes))
            ->withHeader('Content-Disposition', 'attachment; filename="' . rawurlencode($filename) . '"')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'private, no-store');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        try {
            $enforceStudentId = StudentScope::isStudent($request) ? StudentScope::selfId($request) : null;
            $this->service->deleteFile((int) $args['id'], $enforceStudentId);
        } catch (\App\Core\ApiException $e) {
            $response->getBody()->write(json_encode([
                'success'    => false,
                'data'       => null,
                'pagination' => null,
                'error'      => [
                    'code'    => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($e->getHttpStatus())->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => null,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
