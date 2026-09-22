<?php

namespace App\Modules\WeeklyPlans;

class PlanService
{
    public function get(int $studentId): array
    {
        $items = WeeklyPlan::findByStudent($studentId);
        return ['items' => $items, 'student_id' => $studentId];
    }

    public function save(int $studentId, array $items): array
    {
        if (!$studentId) throw new \RuntimeException('شناسه دانش‌آموز الزامی است', 400);
        $count = WeeklyPlan::save($studentId, $items);
        return ['inserted' => $count, 'student_id' => $studentId];
    }

    public function clear(int $studentId): void
    {
        if (!$studentId) throw new \RuntimeException('شناسه دانش‌آموز الزامی است', 400);
        WeeklyPlan::clear($studentId);
    }
}