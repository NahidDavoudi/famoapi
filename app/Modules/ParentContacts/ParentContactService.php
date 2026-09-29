<?php

namespace App\Modules\ParentContacts;

use App\Core\ApiException;
use App\Core\Database;

class ParentContactService
{
    public function list(int $studentId): array
    {
        $this->ensureStudentExists($studentId);
        return ParentContact::findAllForStudent($studentId);
    }

    public function create(int $studentId, array $data): array
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->lockStudent($studentId);
            $data['is_primary'] = !empty($data['is_primary']) || ParentContact::countForStudent($studentId) === 0;
            if ($data['is_primary']) {
                ParentContact::clearPrimaryForStudent($studentId);
            }
            $id = ParentContact::create($studentId, $data);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return ParentContact::findForStudent($studentId, $id);
    }

    public function update(int $studentId, int $id, array $data): array
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->lockStudent($studentId);
            if (!ParentContact::findForStudent($studentId, $id)) {
                throw new ApiException('مخاطب والد یافت نشد', 404, 'NOT_FOUND');
            }
            if (!empty($data['is_primary'])) {
                ParentContact::clearPrimaryForStudent($studentId, $id);
            }
            ParentContact::updateForStudent($studentId, $id, $data);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return ParentContact::findForStudent($studentId, $id);
    }

    public function delete(int $studentId, int $id): void
    {
        $this->ensureStudentExists($studentId);
        if (!ParentContact::deleteForStudent($studentId, $id)) {
            throw new ApiException('مخاطب والد یافت نشد', 404, 'NOT_FOUND');
        }
    }

    private function ensureStudentExists(int $studentId): void
    {
        $stmt = Database::getConnection()->prepare('SELECT id FROM students WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $studentId]);
        if (!$stmt->fetchColumn()) {
            throw new ApiException('دانش‌آموز یافت نشد', 404, 'NOT_FOUND');
        }
    }

    private function lockStudent(int $studentId): void
    {
        $stmt = Database::getConnection()->prepare('SELECT id FROM students WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $studentId]);
        if (!$stmt->fetchColumn()) {
            throw new ApiException('دانش‌آموز یافت نشد', 404, 'NOT_FOUND');
        }
    }
}
