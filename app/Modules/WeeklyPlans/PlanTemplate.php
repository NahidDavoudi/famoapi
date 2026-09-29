<?php

namespace App\Modules\WeeklyPlans;

use App\Core\Database;

class PlanTemplate
{
    public static function findAll(): array
    {
        try {
            $stmt = Database::getConnection()->query('SELECT id, name, student_id, week_date, items_json, created_at, updated_at FROM plan_templates ORDER BY name');
            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) {
                $row['items'] = self::decodeItems($row['items_json'] ?? null);
                unset($row['items_json']);
            }
            unset($row);
            return $rows;
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
            if ($result) {
                $result['items'] = self::decodeItems($result['items_json'] ?? null);
                unset($result['items_json']);
            }
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
            json_encode(self::validateItems($data['items'] ?? []), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): int
    {
        $db = Database::getConnection();
        $fields = [];
        $params = [];
        foreach (['name', 'student_id', 'week_date'] as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = ?";
                $params[] = $data[$col];
            }
        }
        if (isset($data['items'])) {
            $fields[] = 'items_json = ?';
            $params[] = json_encode(self::validateItems($data['items']), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
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
            throw new \App\Core\ApiException('قالب یافت نشد', 404, 'NOT_FOUND');
        }

        $items = $template['items'] ?? self::decodeItems($template['items_json'] ?? null);
        if (!is_array($items)) {
            throw new \App\Core\ApiException('داده‌های قالب نامعتبر است', 400, 'VALIDATION_ERROR');
        }

        $result = WeeklyPlan::save([
            'student_id'   => $studentId,
            'week_date'    => $template['week_date'] ?? date('Y/m/d'),
            'weekly_notes' => null,
            'times'        => [],
            'events'       => $items,
        ]);

        return [
            'inserted'    => $result['saved'],
            'plan_id'     => $result['plan_id'],
            'student_id'  => $studentId,
            'template_id' => $templateId,
        ];
    }

    private static function validateItems(mixed $items): array
    {
        if (!is_array($items)) {
            throw new \App\Core\ApiException('فهرست آیتم‌های قالب نامعتبر است', 422, 'VALIDATION_ERROR');
        }
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \App\Core\ApiException('هر آیتم قالب باید یک شیء باشد', 422, 'VALIDATION_ERROR');
            }
            foreach ($item as $value) {
                if (!is_null($value) && !is_scalar($value)) {
                    throw new \App\Core\ApiException('مقدارهای آیتم قالب باید ساده باشند', 422, 'VALIDATION_ERROR');
                }
            }
        }
        return $items;
    }

    private static function decodeItems(mixed $encoded): array
    {
        $decoded = json_decode((string) $encoded, true);
        if (is_array($decoded)) {
            return self::validateItems($decoded);
        }

        // Existing rows may contain PHP-serialized scalar/array data. Never
        // instantiate classes while providing a controlled read-only fallback.
        $legacy = @unserialize((string) $encoded, ['allowed_classes' => false]);
        if (is_array($legacy)) {
            return self::validateItems($legacy);
        }
        if ($encoded === null || $encoded === '') {
            return [];
        }
        throw new \App\Core\ApiException('داده‌های قالب نامعتبر است', 500, 'TEMPLATE_DATA_ERROR');
    }
}
