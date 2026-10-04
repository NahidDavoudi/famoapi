<?php

namespace App\Modules\Assignments;

use App\Core\ApiException;
use App\Core\Database;
use App\Core\Pagination;
use App\Modules\Students\Student;
use App\Modules\Supporters\Supporter;

/**
 * Module 2: canonical student→supporter assignment management.
 *
 * Enforced invariants:
 *  - at most one active assignment per student (service transaction + DB unique index)
 *  - assignments are deactivated, never deleted, so history is preserved
 */
class AssignmentService
{
    public function listStudents(array $filters, int $page, int $perPage): array
    {
        $total = Assignment::countStudents($filters);
        $pagination = Pagination::build($page, $perPage, $total);

        return [
            'items'      => Assignment::listStudents($filters, $pagination['page'], $pagination['per_page']),
            'pagination' => $pagination,
        ];
    }

    public function assign(int $studentId, int $supporterId, ?int $assignedBy, ?string $note = null): array
    {
        $student = Student::findById($studentId);
        if (!$student) {
            throw new ApiException('دانش‌آموز یافت نشد', 404, 'STUDENT_NOT_FOUND');
        }

        $supporter = Supporter::findById($supporterId);
        if (!$supporter) {
            throw new ApiException('پشتیبان یافت نشد', 404, 'SUPPORTER_NOT_FOUND');
        }
        if (!Supporter::isAccountActive($supporterId)) {
            throw new ApiException('پشتیبان غیرفعال است و قابل تخصیص نیست', 403, 'SUPPORTER_INACTIVE');
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->assignWithinTransaction($studentId, $supporterId, $assignedBy, $note);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $this->currentState($studentId);
    }

    public function unassign(int $studentId, ?int $assignedBy = null): array
    {
        $student = Student::findById($studentId);
        if (!$student) {
            throw new ApiException('دانش‌آموز یافت نشد', 404, 'STUDENT_NOT_FOUND');
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            Assignment::deactivateActive($studentId);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $this->currentState($studentId);
    }

    public function history(int $studentId): array
    {
        $student = Student::findById($studentId);
        if (!$student) {
            throw new ApiException('دانش‌آموز یافت نشد', 404, 'STUDENT_NOT_FOUND');
        }

        return [
            'student' => ['id' => (int) $student['id'], 'name' => $student['name']],
            'history' => Assignment::historyByStudent($studentId),
        ];
    }

    public function supporterStudents(int $supporterId): array
    {
        $supporter = Supporter::findById($supporterId);
        if (!$supporter) {
            throw new ApiException('پشتیبان یافت نشد', 404, 'SUPPORTER_NOT_FOUND');
        }

        return [
            'supporter' => ['id' => (int) $supporter['id'], 'name' => $supporter['name']],
            'students'  => Student::findBySupporter($supporterId),
        ];
    }

    /**
     * Initial fill: assign only when exactly one active supporter matches the
     * student's field and grade. Never picks silently; ambiguous and unmatched
     * students are reported back.
     */
    public function initialFill(bool $dryRun, bool $onlyUnassigned, ?int $assignedBy, array $studentIds = []): array
    {
        $students = Assignment::studentsForInitialFill($onlyUnassigned, $studentIds);
        $supporters = Supporter::findActiveForAssignment();

        $byKey = [];
        foreach ($supporters as $supporter) {
            $scopes = $supporter['scopes'] ?? [];
            if ($scopes === [] && isset($supporter['field'], $supporter['grade'])) {
                $scopes = [['field' => (string) $supporter['field'], 'grade' => (int) $supporter['grade']]];
            }

            foreach ($scopes as $scope) {
                $key = $this->matchKey((string) $scope['field'], (int) $scope['grade']);
                $byKey[$key][(int) $supporter['id']] = [
                    'id'   => (int) $supporter['id'],
                    'name' => (string) $supporter['name'],
                ];
            }
        }

        foreach ($byKey as $key => $candidates) {
            $byKey[$key] = array_values($candidates);
        }

        $assigned = [];
        $ambiguous = [];
        $noMatch = [];

        $db = Database::getConnection();
        if (!$dryRun) {
            $db->beginTransaction();
        }

        try {
            foreach ($students as $student) {
                $key = $this->matchKey((string) $student['field'], (int) $student['grade']);
                $candidates = $byKey[$key] ?? [];

                if (count($candidates) === 0) {
                    $noMatch[] = [
                        'student_id'   => (int) $student['id'],
                        'student_name' => (string) $student['name'],
                        'field'        => $student['field'],
                        'grade'        => (int) $student['grade'],
                    ];
                    continue;
                }

                if (count($candidates) > 1) {
                    $ambiguous[] = [
                        'student_id'   => (int) $student['id'],
                        'student_name' => (string) $student['name'],
                        'field'        => $student['field'],
                        'grade'        => (int) $student['grade'],
                        'candidate_count' => count($candidates),
                        'candidates'   => $candidates,
                    ];
                    continue;
                }

                $supporter = $candidates[0];
                if (!$dryRun) {
                    $this->assignWithinTransaction(
                        (int) $student['id'],
                        $supporter['id'],
                        $assignedBy,
                        'initial-fill'
                    );
                }

                $assigned[] = [
                    'student_id'   => (int) $student['id'],
                    'student_name' => (string) $student['name'],
                    'supporter_id' => $supporter['id'],
                    'supporter_name' => $supporter['name'],
                ];
            }

            if (!$dryRun) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if (!$dryRun && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return [
            'dry_run'   => $dryRun,
            'summary'   => [
                'students_considered' => count($students),
                'assigned'            => count($assigned),
                'ambiguous'           => count($ambiguous),
                'no_match'            => count($noMatch),
            ],
            'assigned'  => $assigned,
            'ambiguous' => $ambiguous,
            'no_match'  => $noMatch,
        ];
    }

    private function assignWithinTransaction(int $studentId, int $supporterId, ?int $assignedBy, ?string $note): void
    {
        $active = Assignment::lockActiveByStudent($studentId);
        if ($active && (int) $active['supporter_id'] === $supporterId) {
            return;
        }

        if ($active) {
            Assignment::deactivateActive($studentId);
        }

        Assignment::insert($studentId, $supporterId, $assignedBy, $note);
    }

    private function currentState(int $studentId): array
    {
        $active = Assignment::findActiveByStudent($studentId);

        return [
            'student_id'   => $studentId,
            'supporter_id' => $active ? (int) $active['supporter_id'] : null,
            'supporter_name' => $active['supporter_name'] ?? null,
            'assigned_at'  => $active['assigned_at'] ?? null,
        ];
    }

    private function matchKey(string $field, int $grade): string
    {
        return $field . '|' . $grade;
    }
}
