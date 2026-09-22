<?php

namespace App\Modules\Students;

use App\Core\Database;
use App\Core\Pagination;

class StudentService
{
    public function list(array $filters, int $page, int $perPage): array
    {
        $total = Student::countAll($filters);
        $pagination = Pagination::build($page, $perPage, $total);

        $items = Student::findAll($filters, $pagination['page'], $perPage);

        return [
            'items'      => $items,
            'pagination' => $pagination,
        ];
    }

    public function create(array $data): array
    {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $studentId = Student::create($data);

            $stmt = $db->prepare(
                'INSERT INTO users (username, password_hash, role, linked_id)
                 VALUES (:username, :password_hash, :role, :linked_id)'
            );
            $stmt->execute([
                'username'      => $data['phone'],
                'password_hash' => password_hash('1234', PASSWORD_DEFAULT),
                'role'          => 'student',
                'linked_id'     => $studentId,
            ]);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        return $this->get($studentId);
    }

    public function get(int $id): array
    {
        $student = Student::findById($id);
        if (!$student) {
            throw new \RuntimeException('دانش‌آموز یافت نشد', 404);
        }
        return $student;
    }

    public function update(int $id, array $data): array
    {
        $student = Student::findById($id);
        if (!$student) {
            throw new \RuntimeException('دانش‌آموز یافت نشد', 404);
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
            throw new \RuntimeException('دانش‌آموز یافت نشد', 404);
        }

        Student::delete($id);
    }

    public function createUserAccount(int $studentId): array
    {
        $student = Student::findById($studentId);
        if (!$student) {
            throw new \RuntimeException('دانش‌آموز یافت نشد', 404);
        }

        if (!empty($student['user_id'])) {
            throw new \RuntimeException('این دانش‌آموز قبلاً حساب کاربری دارد', 409);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO users (username, password_hash, role, linked_id)
             VALUES (:username, :password_hash, :role, :linked_id)'
        );
        $stmt->execute([
            'username'      => $student['phone'],
            'password_hash' => password_hash('1234', PASSWORD_DEFAULT),
            'role'          => 'student',
            'linked_id'     => $studentId,
        ]);

        return $this->get($studentId);
    }

    public function resetPassword(int $studentId): array
    {
        $student = Student::findById($studentId);
        if (!$student) {
            throw new \RuntimeException('دانش‌آموز یافت نشد', 404);
        }

        if (empty($student['user_id'])) {
            throw new \RuntimeException('این دانش‌آموز حساب کاربری ندارد', 400);
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
}