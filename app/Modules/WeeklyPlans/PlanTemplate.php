<?php

namespace App\Modules\WeeklyPlans;

use App\Core\Database;

class PlanTemplate
{
    public static function findAll(): array
    {
        try {
            $stmt = Database::getConnection()->query('SELECT * FROM plan_templates ORDER BY name');
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function findById(int $id): ?array
    {
        try {
            $stmt = Database::getConnection()->prepare('SELECT * FROM plan_templates WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function create(array $data): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO plan_templates (name, student_id, week_date, items_json, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['name'],
            $data['student_id'] ?? null,
            $data['week_date'] ?? null,
            isset($data['items']) ? serialize($data['items']) : ($data['items_json'] ?? ''),
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): int
    {
        $db = Database::getConnection();
        $fields = [];
        $params = [];
        foreach (['name', 'student_id', 'week_date', 'items_json'] as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = ?";
                $params[] = $data[$col];
            }
        }
        if (isset($data['items'])) {
            $fields[] = 'items_json = ?';
            $params[] = serialize($data['items']);
        }
        if (empty($fields)) return 0;
        $params[] = $id;
        $stmt = $db->prepare('UPDATE plan_templates SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ?');
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function delete(int $id): bool
    {
        try {
            $stmt = Database::getConnection()->prepare('DELETE FROM plan_templates WHERE id = ?');
            $stmt->execute([$id]);
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function applyToStudent(int $templateId, int $studentId): array
    {
        $template = self::findById($templateId);
        if (!$template) {
            throw new \RuntimeException('قالب یافت نشد', 404);
        }

        $items = unserialize($template['items_json']);
        if (!is_array($items)) {
            throw new \RuntimeException('داده‌های قالب نامعتبر است', 400);
        }

        $count = WeeklyPlan::save($studentId, $items);
        return ['inserted' => $count, 'student_id' => $studentId, 'template_id' => $templateId];
    }
}