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
        if (empty($data['name'])) throw new \RuntimeException('نام قالب الزامی است', 400);
        $id = PlanTemplate::create($data);
        $template = PlanTemplate::findById($id);
        return $template ?? ['id' => $id];
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