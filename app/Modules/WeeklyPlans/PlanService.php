<?php

namespace App\Modules\WeeklyPlans;

use App\Modules\Students\Student;

class PlanService
{
    private const GRADE_MAP = [
        '7th' => 7, '8th' => 8, '9th' => 9, '10th' => 10, '11th' => 11, '12th' => 12,
    ];

    private const FIELD_MAP = [
        'math'       => 'ریاضی',
        'tajrobi'    => 'تجربی',
        'humanities' => 'انسانی',
        'middle'     => 'راهنمایی',
    ];

    /** List plan summaries for a student. */
    public function listForStudent(int $studentId): array
    {
        if ($studentId <= 0) {
            throw new \RuntimeException('شناسه دانش‌آموز الزامی است', 400);
        }
        return ['plans' => WeeklyPlan::listByStudent($studentId)];
    }

    /** Full plan payload: plan + student + times + events. */
    public function getPlan(int $planId): array
    {
        $plan = WeeklyPlan::findById($planId);
        if (!$plan) {
            throw new \RuntimeException('برنامه یافت نشد', 404);
        }

        return [
            'plan'   => $plan,
            'student'=> [
                'id'          => (int) $plan['student_id'],
                'name'        => $plan['student_name'] ?? null,
                'grade'       => isset($plan['student_grade']) ? (int) $plan['student_grade'] : null,
                'field'       => $plan['student_field'] ?? null,
                'national_id' => $plan['student_national_id'] ?? null,
            ],
            'times'  => WeeklyPlan::decodeTimes($plan),
            'events' => WeeklyPlan::getEvents($planId),
        ];
    }

    /**
     * Save a complete planner payload.
     *
     * The student is resolved from an explicit id, national id, or
     * name+grade; a new student is created when none matches.
     */
    public function saveFull(array $payload): array
    {
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $planId = (int) ($payload['plan_id'] ?? 0);

        $weekDate = trim((string) ($payload['week_date'] ?? $meta['date'] ?? ''));
        $weeklyNotes = $payload['weekly_notes'] ?? $payload['weeklyNotes'] ?? null;
        $times = is_array($payload['times'] ?? null) ? $payload['times'] : [];
        $events = is_array($payload['events'] ?? null) ? $payload['events'] : [];

        $requestedStudentId = (int) ($payload['student_id'] ?? 0);

        $studentId = $this->resolveStudent($requestedStudentId, $meta);

        return WeeklyPlan::save([
            'plan_id'      => $planId,
            'student_id'   => $studentId,
            'week_date'    => $weekDate,
            'weekly_notes' => $weeklyNotes,
            'times'        => $times,
            'events'       => $events,
        ]);
    }

    public function deletePlan(int $planId): void
    {
        if (!WeeklyPlan::delete($planId)) {
            throw new \RuntimeException('برنامه یافت نشد', 404);
        }
    }

    public function clearForStudent(int $studentId): int
    {
        if ($studentId <= 0) {
            throw new \RuntimeException('شناسه دانش‌آموز الزامی است', 400);
        }
        return WeeklyPlan::clearForStudent($studentId);
    }

    private function resolveStudent(int $studentId, array $meta): int
    {
        $db = \App\Core\Database::getConnection();

        $name = trim((string) ($meta['name'] ?? ''));
        $grade = $this->normalizeGrade($meta['grade'] ?? null);
        $field = $this->normalizeField($meta['field'] ?? $meta['major'] ?? null);
        $nationalId = trim((string) ($meta['national_id'] ?? $meta['nationalId'] ?? ''));

        if ($studentId > 0) {
            $student = Student::findById($studentId);
            if (!$student) {
                throw new \RuntimeException('دانش‌آموز یافت نشد', 404);
            }
            $this->applyStudentUpdates($studentId, $name, $grade, $field, $nationalId);
            return $studentId;
        }

        $foundId = 0;
        if ($nationalId !== '') {
            $stmt = $db->prepare('SELECT id FROM students WHERE national_id = :national_id LIMIT 1');
            $stmt->execute(['national_id' => $nationalId]);
            $foundId = (int) $stmt->fetchColumn();
        }

        if ($foundId <= 0 && $name !== '' && $grade > 0) {
            $stmt = $db->prepare('SELECT id FROM students WHERE name = :name AND grade = :grade LIMIT 1');
            $stmt->execute(['name' => $name, 'grade' => $grade]);
            $foundId = (int) $stmt->fetchColumn();
        }

        if ($foundId > 0) {
            $this->applyStudentUpdates($foundId, $name, $grade, $field, $nationalId);
            return $foundId;
        }

        if ($name === '' || $grade <= 0) {
            throw new \RuntimeException('نام و پایه دانش‌آموز الزامی است', 400);
        }

        return Student::create([
            'name'        => $name,
            'grade'       => $grade,
            'field'       => $field,
            'phone'       => null,
            'national_id' => $nationalId !== '' ? $nationalId : null,
        ]);
    }

    private function applyStudentUpdates(int $studentId, string $name, int $grade, ?string $field, string $nationalId): void
    {
        $updates = [];
        if ($name !== '') {
            $updates['name'] = $name;
        }
        if ($grade > 0) {
            $updates['grade'] = $grade;
        }
        if ($field !== null && $field !== '') {
            $updates['field'] = $field;
        }
        if ($nationalId !== '') {
            $updates['national_id'] = $nationalId;
        }

        if (!empty($updates)) {
            Student::update($studentId, $updates);
        }
    }

    private function normalizeGrade(mixed $grade): int
    {
        if ($grade === null || $grade === '') {
            return 0;
        }
        if (is_string($grade) && isset(self::GRADE_MAP[$grade])) {
            return self::GRADE_MAP[$grade];
        }
        $numeric = (int) $grade;
        return ($numeric >= 7 && $numeric <= 12) ? $numeric : 0;
    }

    private function normalizeField(mixed $field): ?string
    {
        if ($field === null || $field === '') {
            return null;
        }
        $field = trim((string) $field);
        return self::FIELD_MAP[$field] ?? $field;
    }

    // ── Templates ──────────────────────────────────────────────

    public function getTemplates(): array
    {
        return PlanTemplate::findAll();
    }

    public function getTemplate(int $id): ?array
    {
        return PlanTemplate::findById($id);
    }

    public function saveTemplate(array $data): array
    {
        if (empty($data['name'])) {
            throw new \RuntimeException('نام قالب الزامی است', 400);
        }
        $id = PlanTemplate::create($data);
        return PlanTemplate::findById($id) ?? ['id' => $id];
    }

    public function deleteTemplate(int $id): void
    {
        PlanTemplate::delete($id);
    }

    public function applyTemplate(int $templateId, int $studentId): array
    {
        return PlanTemplate::applyToStudent($templateId, $studentId);
    }
}
