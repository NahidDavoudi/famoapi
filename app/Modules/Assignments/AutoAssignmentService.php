<?php
declare(strict_types=1);

namespace App\Modules\Assignments;

use App\Core\Database;
use App\Modules\Students\Student;
use App\Modules\Supporters\Supporter;

/**
 * Automatic student↔supporter assignment used by the Telegram linking flows.
 *
 * Supporter → student: every active, currently unassigned student matching one
 * of the supporter's scopes is assigned.
 *
 * Student → supporter: the student is assigned only when exactly one active
 * supporter matches the student's (field, grade); zero or multiple matches are
 * left untouched (no guessing), consistent with the initial-fill semantics.
 *
 * All work runs inside a transaction and is idempotent: an existing active
 * assignment is never overwritten.
 */
class AutoAssignmentService
{
    /**
     * Entry point for LinkService: dispatch by the role being linked.
     *
     * @return array<string,mixed>
     */
    public function autoAssignOnLink(string $role, int $accountId): array
    {
        if ($role === 'supporter') {
            return $this->assignSupporterScope($accountId);
        }
        if ($role === 'student') {
            return $this->assignStudent($accountId);
        }

        return ['role' => $role, 'account_id' => $accountId, 'assigned' => 0];
    }

    /**
     * @return array{supporter_id:int,status:string,assigned:int,student_ids:int[]}
     */
    public function assignSupporterScope(int $supporterId): array
    {
        $supporter = Supporter::findById($supporterId);
        if (!$supporter || !Supporter::isAccountActive($supporterId)) {
            return ['supporter_id' => $supporterId, 'status' => 'inactive', 'assigned' => 0, 'student_ids' => []];
        }

        $scopes = Supporter::findScopes($supporterId);
        if ($scopes === [] && ($supporter['field'] ?? null) !== null && ($supporter['grade'] ?? null) !== null) {
            $scopes = [['field' => (string) $supporter['field'], 'grade' => (int) $supporter['grade']]];
        }
        if ($scopes === []) {
            return ['supporter_id' => $supporterId, 'status' => 'no_scopes', 'assigned' => 0, 'student_ids' => []];
        }

        $students = Assignment::unassignedActiveStudentsForScopes($scopes);

        $studentIds = $this->withTransaction(function (\PDO $db) use ($students, $supporterId): array {
            $assigned = [];
            foreach ($students as $student) {
                $active = Assignment::lockActiveByStudent((int) $student['id']);
                if ($active) {
                    continue;
                }
                Assignment::insert((int) $student['id'], $supporterId, null, 'auto-link');
                $assigned[] = (int) $student['id'];
            }

            return $assigned;
        });

        return [
            'supporter_id' => $supporterId,
            'status'       => 'ok',
            'assigned'     => count($studentIds),
            'student_ids'  => $studentIds,
        ];
    }

    /**
     * @return array{student_id:int,status:string,assigned:int,supporter_id?:int}
     */
    public function assignStudent(int $studentId): array
    {
        $student = Student::findById($studentId);
        if (!$student || (int) ($student['is_active'] ?? 0) !== 1) {
            return ['student_id' => $studentId, 'status' => 'not_found', 'assigned' => 0];
        }

        $field = (string) ($student['field'] ?? '');
        $grade = (int) ($student['grade'] ?? 0);

        $matches = [];
        foreach (Supporter::findActiveForAssignment() as $supporter) {
            foreach ($supporter['scopes'] as $scope) {
                if ((string) $scope['field'] === $field && (int) $scope['grade'] === $grade) {
                    $matches[(int) $supporter['id']] = $supporter;
                    break;
                }
            }
        }

        if (count($matches) === 0) {
            return ['student_id' => $studentId, 'status' => 'no_match', 'assigned' => 0];
        }
        if (count($matches) > 1) {
            return [
                'student_id'      => $studentId,
                'status'          => 'ambiguous',
                'assigned'        => 0,
                'candidate_count' => count($matches),
            ];
        }

        $supporter = reset($matches);

        return $this->withTransaction(function (\PDO $db) use ($studentId, $supporter): array {
            $active = Assignment::lockActiveByStudent($studentId);
            if ($active) {
                return [
                    'student_id'   => $studentId,
                    'status'       => 'already_assigned',
                    'assigned'     => 0,
                    'supporter_id' => (int) $active['supporter_id'],
                ];
            }

            Assignment::insert($studentId, (int) $supporter['id'], null, 'auto-link');

            return [
                'student_id'   => $studentId,
                'status'       => 'assigned',
                'assigned'     => 1,
                'supporter_id' => (int) $supporter['id'],
            ];
        });
    }

    /**
     * @template T
     * @param callable(\PDO):T $callback
     * @return T
     */
    private function withTransaction(callable $callback)
    {
        $db = Database::getConnection();
        $startedTransaction = !$db->inTransaction();
        if ($startedTransaction) {
            $db->beginTransaction();
        }

        try {
            $result = $callback($db);
            if ($startedTransaction) {
                $db->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if ($startedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
