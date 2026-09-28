<?php

namespace App\Modules\Exams;

use App\Core\Pagination;

class ExamService
{
    public function getDates(int $page, int $perPage, ?int $studentId = null): array
    {
        $total = ExamResult::countDates($studentId);
        $pagination = Pagination::build($page, $perPage, $total);
        $dates = ExamResult::findDates($studentId, $pagination['page'], $perPage);
        return ['dates' => $dates, 'pagination' => $pagination];
    }

    public function getStudentsByDate(string $examDate, int $page, int $perPage): array
    {
        $total = ExamResult::countStudentsByDate($examDate);
        $pagination = Pagination::build($page, $perPage, $total);
        $students = ExamResult::findStudentsByDate($examDate, $pagination['page'], $perPage);
        return ['students' => $students, 'pagination' => $pagination];
    }

    public function getDetails(string $examDate, int $studentId): array
    {
        $result = ExamResult::findDetails($examDate, $studentId);
        if (!$result['student']) throw new \RuntimeException('دانش‌آموز یافت نشد', 404);
        return $result;
    }

    public function getAll(array $filters, int $page, int $perPage): array
    {
        $total = ExamResult::countAll($filters);
        $pagination = Pagination::build($page, $perPage, $total);
        $exams = ExamResult::findAll($filters, $pagination['page'], $perPage);
        return ['exams' => $exams, 'pagination' => $pagination];
    }

    public function save(int $studentId, string $examDate, array $subjects): array
    {
        if (!$studentId || !$examDate || empty($subjects)) {
            throw new \RuntimeException('داده‌های ناقص', 400);
        }
        $inserted = ExamResult::saveBulk($studentId, $examDate, $subjects);
        return ['inserted' => $inserted];
    }
}