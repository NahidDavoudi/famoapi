<?php

namespace App\Modules\Students;

use App\Core\Database;
use App\Core\Pagination;
use App\Core\ApiException;
use App\Modules\Bot\PhoneNormalizer;

class StudentService
{
    public function list(array $filters, int $page, int $perPage): array
    {
        $total = Student::countAll($filters);
        $pagination = Pagination::build($page, $perPage, $total);

        $items = Student::findAll($filters, $pagination['page'], $pagination['per_page']);

        return [
            'items'      => $items,
            'pagination' => $pagination,
        ];
    }

    public function create(array $data): array
    {
        $data['phone'] = $this->normalizePhone((string) ($data['phone'] ?? ''));
        $data['supporter_id'] = $this->supporterIdForField((string) ($data['field'] ?? ''));
        if ($this->phoneIsRegistered($data['phone'])) {
            throw new ApiException('این شماره تلفن قبلاً ثبت شده است', 409, 'PHONE_ALREADY_REGISTERED');
        }
        $db = Database::getConnection();
        $startedTransaction = !$db->inTransaction();
        if ($startedTransaction) {
            $db->beginTransaction();
        }

        try {
            $studentId = Student::create($data);

            $stmt = $db->prepare(
                'INSERT INTO users (full_name, username, password_hash, role, linked_id, chat_id, supporter_id)
                 VALUES (:full_name, :username, :password_hash, :role, :linked_id, :chat_id, :supporter_id)'
            );
            $stmt->execute([
                'full_name'     => $data['full_name'] ?? $data['name'],
                'username'      => $data['phone'],
                'password_hash' => password_hash($data['password'] ?? '1234', PASSWORD_DEFAULT),
                'role'          => 'student',
                'linked_id'     => $studentId,
                'chat_id'       => $data['chat_id'] ?? null,
                'supporter_id'  => $data['supporter_id'],
            ]);
            if ($startedTransaction) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $this->get($studentId);
    }

    public function get(int $id): array
    {
        $student = Student::findById($id);
        if (!$student) {
            throw new \App\Core\ApiException('دانش‌آموز یافت نشد', 404, 'NOT_FOUND');
        }
        return $student;
    }

    public function update(int $id, array $data): array
    {
        $student = Student::findById($id);
        if (!$student) {
            throw new \App\Core\ApiException('دانش‌آموز یافت نشد', 404, 'NOT_FOUND');
        }

        Student::update($id, $data);

        if (isset($data['phone']) && $data['phone'] !== $student['phone']) {
            $stmt = Database::getConnection()->prepare(
                'UPDATE users SET username = :username WHERE linked_id = :linked_id AND role = :role'
            );
            $stmt->execute([
                'username'  => $data['phone'],
                'linked_id' => $id,
                'role'      => 'student',
            ]);
        }

        return $this->get($id);
    }

    public function delete(int $id): void
    {
        $student = Student::findById($id);
        if (!$student) {
            throw new \App\Core\ApiException('دانش‌آموز یافت نشد', 404, 'NOT_FOUND');
        }

        Student::delete($id);
    }

    public function createUserAccount(int $studentId): array
    {
        $student = Student::findById($studentId);
        if (!$student) {
            throw new \App\Core\ApiException('دانش‌آموز یافت نشد', 404, 'NOT_FOUND');
        }

        if (!empty($student['user_id'])) {
            throw new \App\Core\ApiException('این دانش‌آموز قبلاً حساب کاربری دارد', 409, 'ACCOUNT_ERROR');
        }

        $db = Database::getConnection();
        $phone = $this->normalizePhone((string) ($student['phone'] ?? ''));
        if ($this->phoneIsRegistered($phone, $studentId)) {
            throw new ApiException('این شماره تلفن قبلاً ثبت شده است', 409, 'PHONE_ALREADY_REGISTERED');
        }
        $stmt = $db->prepare(
            'INSERT INTO users (full_name, username, password_hash, role, linked_id, supporter_id)
             VALUES (:full_name, :username, :password_hash, :role, :linked_id, :supporter_id)'
        );
        $stmt->execute([
            'full_name'     => $student['name'],
            'username'      => $phone,
            'password_hash' => password_hash('1234', PASSWORD_DEFAULT),
            'role'          => 'student',
            'linked_id'     => $studentId,
            'supporter_id'  => $this->supporterIdForField((string) $student['field']),
        ]);

        return $this->get($studentId);
    }

    public function normalizePhone(string $phone): string
    {
        $normalized = PhoneNormalizer::normalize($phone);
        if ($normalized === null) {
            throw new ApiException('شماره تلفن معتبر نیست', 422, 'VALIDATION_ERROR');
        }

        return $normalized;
    }

    public function phoneIsRegistered(string $phone, ?int $exceptStudentId = null): bool
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT 1 FROM users WHERE username = :phone LIMIT 1'
        );
        $stmt->execute(['phone' => $phone]);
        if ($stmt->fetchColumn() !== false) {
            return true;
        }

        $sql = 'SELECT 1 FROM students WHERE phone = :phone';
        $params = ['phone' => $phone];
        if ($exceptStudentId !== null) {
            $sql .= ' AND id <> :except_student_id';
            $params['except_student_id'] = $exceptStudentId;
        }
        $stmt = Database::getConnection()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    public function supporterIdForField(string $field): ?int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id FROM supporters
             WHERE field = :field AND is_active = 1 AND id > 0
             ORDER BY id ASC
             LIMIT 1'
        );
        $stmt->execute(['field' => $field]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function resetPassword(int $studentId): array
    {
        $student = Student::findById($studentId);
        if (!$student) {
            throw new \App\Core\ApiException('دانش‌آموز یافت نشد', 404, 'NOT_FOUND');
        }

        if (empty($student['user_id'])) {
            throw new \App\Core\ApiException('این دانش‌آموز حساب کاربری ندارد', 400, 'RESET_ERROR');
        }

        $stmt = Database::getConnection()->prepare(
            'UPDATE users SET password_hash = :password WHERE id = :id'
        );
        $stmt->execute([
            'password' => password_hash('1234', PASSWORD_DEFAULT),
            'id'       => $student['user_id'],
        ]);

        return $this->get($studentId);
    }

    public function getList(): array
    {
        return Student::getList();
    }

    public function getPlannerOverview(): array
    {
        return Student::plannerOverview();
    }

    public function toggleStatus(int $id): array
    {
        $student = Student::findById($id);
        if (!$student) {
            throw new \App\Core\ApiException('دانش‌آموز یافت نشد', 404, 'NOT_FOUND');
        }

        Student::toggleStatus($id);

        return $this->get($id);
    }

    public function getAnalyticsSummary(int $studentId): array
    {
        $student = Student::findById($studentId);
        if (!$student) {
            throw new \App\Core\ApiException('دانش‌آموز یافت نشد', 404, 'NOT_FOUND');
        }

        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT COUNT(*) FROM events WHERE student_id = ?');
        $stmt->execute([$studentId]);
        $planCount = (int) $stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM exam_results WHERE student_id = ?');
        $stmt->execute([$studentId]);
        $examCount = (int) $stmt->fetchColumn();

        $stmt = $db->prepare('SELECT ROUND(AVG(percentage), 1) FROM exam_results WHERE student_id = ?');
        $stmt->execute([$studentId]);
        $avgPercentage = (float) ($stmt->fetchColumn() ?: 0);

        $stmt = $db->prepare(
            'SELECT er.*, s.name AS student_name
             FROM exam_results er
             JOIN students s ON er.student_id = s.id
             WHERE er.student_id = ?
             ORDER BY er.exam_date DESC
             LIMIT 5'
        );
        $stmt->execute([$studentId]);
        $recentExams = $stmt->fetchAll();

        $stmt = $db->prepare(
            'SELECT subject, ROUND(AVG(percentage), 1) AS avg_percentage, COUNT(*) AS total_exams
             FROM exam_results
             WHERE student_id = ?
             GROUP BY subject
             ORDER BY avg_percentage ASC
             LIMIT 1'
        );
        $stmt->execute([$studentId]);
        $weakest = $stmt->fetch();

        $stmt = $db->prepare(
            'SELECT subject, ROUND(AVG(percentage), 1) AS avg_percentage, COUNT(*) AS total_exams
             FROM exam_results
             WHERE student_id = ?
             GROUP BY subject
             ORDER BY avg_percentage DESC
             LIMIT 1'
        );
        $stmt->execute([$studentId]);
        $strongest = $stmt->fetch();

        return [
            'student'          => [
                'id'    => $student['id'],
                'name'  => $student['name'],
                'grade' => $student['grade'],
                'field' => $student['field'],
            ],
            'plan_count'       => $planCount,
            'exam_count'       => $examCount,
            'avg_percentage'   => $avgPercentage,
            'recent_exams'     => $recentExams,
            'weakest_subject'  => $weakest ?: null,
            'strongest_subject' => $strongest ?: null,
        ];
    }

    public function getAnalyticsWeekDetail(int $studentId): array
    {
        $student = Student::findById($studentId);
        if (!$student) {
            throw new \App\Core\ApiException('دانش‌آموز یافت نشد', 404, 'NOT_FOUND');
        }

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT * FROM events WHERE student_id = ? ORDER BY day_index ASC, time_index ASC'
        );
        $stmt->execute([$studentId]);
        $events = $stmt->fetchAll();

        $DAY_NAMES = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
        $schedule = [];
        foreach ($DAY_NAMES as $day) {
            $schedule[$day] = [];
        }
        foreach ($events as $ev) {
            $dayName = $ev['day_name'];
            if (!isset($schedule[$dayName])) {
                continue;
            }
            $schedule[$dayName][] = [
                'time'     => $ev['time_label'],
                'subject'  => $ev['subject_name'],
                'topic'    => $ev['topic_label'],
                'activity' => $ev['title'],
                'notes'    => $ev['notes'],
                'color'    => $ev['color'],
            ];
        }

        return [
            'student'  => ['id' => $student['id'], 'name' => $student['name']],
            'schedule' => $schedule,
            'total'    => count($events),
        ];
    }

    public function getAnalyticsSubjectStats(int $studentId): array
    {
        $student = Student::findById($studentId);
        if (!$student) {
            throw new \App\Core\ApiException('دانش‌آموز یافت نشد', 404, 'NOT_FOUND');
        }

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT subject, ROUND(AVG(percentage), 1) AS avg_percentage, COUNT(*) AS total_exams
             FROM exam_results
             WHERE student_id = ?
             GROUP BY subject
             ORDER BY avg_percentage DESC'
        );
        $stmt->execute([$studentId]);
        $subjects = $stmt->fetchAll();

        return [
            'student'  => ['id' => $student['id'], 'name' => $student['name']],
            'subjects' => $subjects,
        ];
    }

    public function getAnalyticsExamTrend(int $studentId): array
    {
        $student = Student::findById($studentId);
        if (!$student) {
            throw new \App\Core\ApiException('دانش‌آموز یافت نشد', 404, 'NOT_FOUND');
        }

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT exam_date, ROUND(AVG(percentage), 1) AS avg_percentage
             FROM exam_results
             WHERE student_id = ?
             GROUP BY exam_date
             ORDER BY exam_date ASC'
        );
        $stmt->execute([$studentId]);
        $trend = $stmt->fetchAll();

        return [
            'student' => ['id' => $student['id'], 'name' => $student['name']],
            'trend'   => $trend,
        ];
    }
}
