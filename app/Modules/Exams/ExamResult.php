<?php

namespace App\Modules\Exams;

use App\Core\Database;

class ExamResult
{
    public static function findDates(?int $studentId, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $where = $studentId ? 'WHERE student_id = ?' : '';
        $params = $studentId ? [$studentId, $perPage, $offset] : [$perPage, $offset];

        $stmt = Database::getConnection()->prepare(
            "SELECT exam_date, COUNT(DISTINCT student_id) as student_count,
                    COUNT(DISTINCT subject) as subject_count,
                    ROUND(AVG(percentage), 1) as avg_percentage
             FROM exam_results
             {$where}
             GROUP BY exam_date
             ORDER BY exam_date DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function countDates(?int $studentId): int
    {
        $db = Database::getConnection();
        if ($studentId) {
            $stmt = $db->prepare('SELECT COUNT(DISTINCT exam_date) FROM exam_results WHERE student_id = ?');
            $stmt->execute([$studentId]);
        } else {
            $stmt = $db->query('SELECT COUNT(DISTINCT exam_date) FROM exam_results');
        }
        return (int) $stmt->fetchColumn();
    }

    public static function findStudentsByDate(string $examDate, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = Database::getConnection()->prepare(
            'SELECT s.id as student_id, s.name, s.grade, s.field,
                    COUNT(er.id) as subject_count,
                    ROUND(AVG(er.percentage), 1) as avg_percentage
             FROM students s
             INNER JOIN exam_results er ON s.id = er.student_id
             WHERE er.exam_date = ?
             GROUP BY s.id, s.name, s.grade, s.field
             ORDER BY s.name
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$examDate, $perPage, $offset]);
        return $stmt->fetchAll();
    }

    public static function countStudentsByDate(string $examDate): int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(DISTINCT student_id) FROM exam_results WHERE exam_date = ?'
        );
        $stmt->execute([$examDate]);
        return (int) $stmt->fetchColumn();
    }

    public static function findDetails(string $examDate, int $studentId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, name, grade, field FROM students WHERE id = ?');
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();

        if (!$student) return ['student' => null, 'subjects' => [], 'avg_percentage' => 0];

        $stmt = $db->prepare(
            'SELECT subject, chapter, total_q, correct, wrong, skipped, percentage
             FROM exam_results WHERE student_id = ? AND exam_date = ? ORDER BY subject'
        );
        $stmt->execute([$studentId, $examDate]);
        $subjects = $stmt->fetchAll();

        $stmt = $db->prepare(
            'SELECT ROUND(AVG(percentage), 1) as avg FROM exam_results WHERE student_id = ? AND exam_date = ?'
        );
        $stmt->execute([$studentId, $examDate]);
        $avg = $stmt->fetchColumn();

        return ['student' => $student, 'subjects' => $subjects, 'avg_percentage' => $avg ?: 0];
    }

    public static function findAll(array $filters, int $page, int $perPage): array
    {
        $conditions = ['1=1'];
        $params = [];
        $offset = ($page - 1) * $perPage;

        if (!empty($filters['student_id'])) {
            $conditions[] = 'er.student_id = ?';
            $params[] = (int) $filters['student_id'];
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = 'er.exam_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'er.exam_date <= ?';
            $params[] = $filters['date_to'];
        }

        $sql = 'SELECT er.*, s.name as student_name, s.field, s.grade, f.file_path
                FROM exam_results er
                JOIN students s ON er.student_id = s.id
                LEFT JOIN files f ON er.file_id = f.id
                WHERE ' . implode(' AND ', $conditions) . '
                ORDER BY er.exam_date DESC, s.name
                LIMIT ? OFFSET ?';

        $stmt = Database::getConnection()->prepare($sql);
        array_push($params, $perPage, $offset);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function countAll(array $filters): int
    {
        $conditions = ['1=1'];
        $params = [];

        if (!empty($filters['student_id'])) {
            $conditions[] = 'er.student_id = ?';
            $params[] = (int) $filters['student_id'];
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = 'er.exam_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'er.exam_date <= ?';
            $params[] = $filters['date_to'];
        }

        $sql = 'SELECT COUNT(*) FROM exam_results er WHERE ' . implode(' AND ', $conditions);
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function saveBulk(int $studentId, string $examDate, array $subjects): int
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $inserted = 0;
            $stmt = $db->prepare(
                'INSERT INTO exam_results (student_id, exam_date, subject, chapter, total_q, correct, wrong, skipped)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($subjects as $subj) {
                if (empty($subj['subject']) || !isset($subj['total_q'])) continue;
                $total = (int) $subj['total_q'];
                $correct = (int) ($subj['correct'] ?? 0);
                $wrong = (int) ($subj['wrong'] ?? 0);
                $skipped = (int) ($subj['skipped'] ?? 0);
                if ($correct + $wrong + $skipped > $total) continue;
                $stmt->execute([
                    $studentId, $examDate,
                    $subj['subject'],
                    $subj['chapter'] ?? null,
                    $total, $correct, $wrong, $skipped
                ]);
                $inserted++;
            }
            $db->commit();
            return $inserted;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}