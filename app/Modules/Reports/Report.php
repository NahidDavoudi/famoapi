<?php

namespace App\Modules\Reports;

use App\Core\Database;

class Report
{
    public static function findAll(?string $dateFrom, ?string $dateTo, int $page, int $perPage): array
    {
        $conditions = ['1=1'];
        $params = [];
        $offset = ($page - 1) * $perPage;

        if ($dateFrom) {
            $conditions[] = 'r.report_date >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $conditions[] = 'r.report_date <= ?';
            $params[] = $dateTo;
        }

        $sql = 'SELECT r.*, s.name as student_name, s.grade, s.field,
                       sp.name as supporter_name
                FROM reports_status r
                JOIN students s ON r.student_id = s.id
                LEFT JOIN supporters sp ON r.supporter_id = sp.id
                WHERE ' . implode(' AND ', $conditions) . '
                ORDER BY r.report_date DESC
                LIMIT ? OFFSET ?';

        $stmt = Database::getConnection()->prepare($sql);
        array_push($params, $perPage, $offset);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function countAll(?string $dateFrom, ?string $dateTo): int
    {
        $conditions = ['1=1'];
        $params = [];

        if ($dateFrom) { $conditions[] = 'report_date >= ?'; $params[] = $dateFrom; }
        if ($dateTo) { $conditions[] = 'report_date <= ?'; $params[] = $dateTo; }

        $sql = 'SELECT COUNT(*) FROM reports_status WHERE ' . implode(' AND ', $conditions);
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function getStats(): array
    {
        $stmt = Database::getConnection()->query(
            'SELECT DATE(report_date) as date,
                    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as replied,
                    SUM(CASE WHEN status = 0 OR status IS NULL THEN 1 ELSE 0 END) as pending
             FROM reports_status
             GROUP BY DATE(report_date)
             ORDER BY date DESC
             LIMIT 30'
        );
        return $stmt->fetchAll();
    }
}