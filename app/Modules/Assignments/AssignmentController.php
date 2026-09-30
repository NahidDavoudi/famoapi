<?php

namespace App\Modules\Assignments;

use App\Core\ApiException;
use App\Core\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Module 2: admin endpoints for canonical student→supporter assignments.
 * Protected by the existing admin JWT + role middleware.
 */
class AssignmentController
{
    public function __construct(private AssignmentService $service)
    {
    }

    public function listStudents(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $filters = [
            'status'           => $query['status'] ?? 'all',
            'search'           => $query['search'] ?? null,
            'field'            => $query['field'] ?? null,
            'grade'            => $query['grade'] ?? null,
            'supporter_id'     => $query['supporter_id'] ?? null,
            'include_inactive' => filter_var($query['include_inactive'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
        $page = (int) ($query['page'] ?? 1);
        $perPage = (int) ($query['perPage'] ?? 20);

        $result = $this->service->listStudents($filters, $page, $perPage);

        return ResponseHelper::paginated($response, $result['items'], $result['pagination']);
    }

    public function assign(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $studentId = (int) ($body['student_id'] ?? 0);
        $supporterId = (int) ($body['supporter_id'] ?? 0);
        $note = isset($body['note']) ? (string) $body['note'] : null;

        if ($studentId <= 0 || $supporterId <= 0) {
            throw new ApiException('شناسه دانش‌آموز و پشتیبان الزامی است', 422, 'VALIDATION_ERROR');
        }

        $result = $this->service->assign($studentId, $supporterId, $this->actorId($request), $note);

        return ResponseHelper::json($response, $result);
    }

    public function unassign(Request $request, Response $response, array $args): Response
    {
        $result = $this->service->unassign((int) $args['studentId'], $this->actorId($request));

        return ResponseHelper::json($response, $result);
    }

    public function supporterStudents(Request $request, Response $response, array $args): Response
    {
        return ResponseHelper::json($response, $this->service->supporterStudents((int) $args['supporterId']));
    }

    public function history(Request $request, Response $response, array $args): Response
    {
        return ResponseHelper::json($response, $this->service->history((int) $args['studentId']));
    }

    public function initialFill(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $dryRun = array_key_exists('dry_run', $body)
            ? filter_var($body['dry_run'], FILTER_VALIDATE_BOOLEAN)
            : true;
        $onlyUnassigned = array_key_exists('only_unassigned', $body)
            ? filter_var($body['only_unassigned'], FILTER_VALIDATE_BOOLEAN)
            : true;

        // Optional scoping: apply only to the given student ids.
        $studentIds = [];
        if (isset($body['student_ids']) && is_array($body['student_ids'])) {
            $studentIds = $body['student_ids'];
        }

        $result = $this->service->initialFill($dryRun, $onlyUnassigned, $this->actorId($request), $studentIds);

        return ResponseHelper::json($response, $result);
    }

    private function actorId(Request $request): ?int
    {
        $user = $request->getAttribute('user');
        $id = $user->sub ?? null;

        return $id !== null ? (int) $id : null;
    }
}
