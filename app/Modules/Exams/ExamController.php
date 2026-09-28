<?php

namespace App\Modules\Exams;

use App\Core\StudentScope;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ExamController
{
    public function __construct(private ExamService $service) {}

    public function getDates(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['per_page'] ?? 20);
        $studentId = StudentScope::studentId(
            $request,
            isset($params['student_id']) ? (int) $params['student_id'] : null
        );
        $result = $this->service->getDates($page, $perPage, $studentId);

        $response->getBody()->write(json_encode([
            'success' => true, 'data' => ['dates' => $result['dates']],
            'pagination' => $result['pagination'], 'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function getStudents(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $examDate = $params['exam_date'] ?? '';
        if (!$examDate) {
            $response->getBody()->write(json_encode([
                'success' => false, 'data' => null, 'pagination' => null,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'تاریخ الزامی است'],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400);
        }
        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['per_page'] ?? 20);
        $result = $this->service->getStudentsByDate($examDate, $page, $perPage);

        $response->getBody()->write(json_encode([
            'success' => true, 'data' => ['students' => $result['students']],
            'pagination' => $result['pagination'], 'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function getDetails(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $examDate = $params['exam_date'] ?? '';
        $studentId = StudentScope::studentId(
            $request,
            isset($params['student_id']) ? (int) $params['student_id'] : null
        );
        if (!$examDate || !$studentId) {
            $response->getBody()->write(json_encode([
                'success' => false, 'data' => null, 'pagination' => null,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'پارامترها ناقص است'],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400);
        }

        try {
            $result = $this->service->getDetails($examDate, (int) $studentId);
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false, 'data' => null, 'pagination' => null,
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(404);
        }

        $response->getBody()->write(json_encode([
            'success' => true, 'data' => $result, 'pagination' => null, 'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function getAll(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $filters = [
            'student_id' => StudentScope::studentId(
                $request,
                isset($params['student_id']) ? (int) $params['student_id'] : null
            ),
            'date_from' => $params['date_from'] ?? null,
            'date_to' => $params['date_to'] ?? null,
        ];
        $page = (int) ($params['page'] ?? 1);
        $perPage = (int) ($params['per_page'] ?? 200);
        $result = $this->service->getAll($filters, $page, $perPage);

        $response->getBody()->write(json_encode([
            'success' => true, 'data' => ['exams' => $result['exams']],
            'pagination' => $result['pagination'], 'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }

    public function save(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $studentId = (int) ($body['student_id'] ?? 0);
        $examDate = $body['exam_date'] ?? '';
        $subjects = $body['subjects'] ?? [];

        try {
            $result = $this->service->save($studentId, $examDate, $subjects);
        } catch (\RuntimeException $e) {
            $code = $e->getCode() ?: 400;
            $response->getBody()->write(json_encode([
                'success' => false, 'data' => null, 'pagination' => null,
                'error' => ['code' => 'ERROR', 'message' => $e->getMessage()],
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus($code);
        }

        $response->getBody()->write(json_encode([
            'success' => true, 'data' => $result, 'pagination' => null, 'error' => null,
        ], JSON_UNESCAPED_UNICODE));
        return $response;
    }
}