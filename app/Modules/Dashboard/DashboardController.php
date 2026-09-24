<?php

namespace App\Modules\Dashboard;

use App\Core\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DashboardController
{
    public function stats(Request $request, Response $response): Response
    {
        $db = Database::getConnection();

        $stats = [];
        $stats['students_count'] = (int) $db->query('SELECT COUNT(*) FROM students')->fetchColumn();

        $weekStart = date('Y-m-d', strtotime('saturday last week'));
        $stmt = $db->prepare('SELECT COUNT(DISTINCT exam_date) FROM exam_results WHERE exam_date >= ?');
        $stmt->execute([$weekStart]);
        $stats['exams_this_week'] = (int) $stmt->fetchColumn();

        $stats['students_no_exam'] = (int) $db->query(
            'SELECT COUNT(DISTINCT s.id) FROM students s
             LEFT JOIN exam_results er ON s.id = er.student_id
             WHERE er.id IS NULL'
        )->fetchColumn();

        $stats['pending_reports'] = (int) $db->query(
            "SELECT COUNT(*) FROM reports_status WHERE status = 0 OR status IS NULL OR status = 'pending'"
        )->fetchColumn();

        $stats['avg_by_field'] = $db->query(
            'SELECT s.field,
                    ROUND(AVG(er.percentage), 1) AS avg_percentage,
                    COUNT(DISTINCT s.id) AS student_count
             FROM students s
             INNER JOIN exam_results er ON s.id = er.student_id
             GROUP BY s.field'
        )->fetchAll();

        $response->getBody()->write(json_encode([
            'success'    => true,
            'data'       => $stats,
            'pagination' => null,
            'error'      => null,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
