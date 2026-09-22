<?php

namespace App\Modules\Remedial;

class RemedialService
{
    public function getSessions(): array
    {
        return Remedial::findSessions();
    }

    public function createSession(array $data): array
    {
        $id = Remedial::createSession($data);
        if (!$id) {
            throw new \RuntimeException('ایجاد جلسه جبرانی امکان‌پذیر نیست', 500);
        }
        return Remedial::findSessionById($id);
    }

    public function getSessionData(int $sessionId): array
    {
        $session = Remedial::findSessionById($sessionId);
        if (!$session) {
            throw new \RuntimeException('جلسه جبرانی یافت نشد', 404);
        }

        $fields = ['ریاضی', 'تجربی', 'انسانی', 'زبان', 'هنر'];
        $classes = [];
        foreach ($fields as $field) {
            $classes[$field] = Remedial::getClassesByField($field, $sessionId);
        }

        return [
            'session' => $session,
            'classes' => $classes,
        ];
    }

    public function toggleAttendance(int $sessionId, int $classId, int $studentId): array
    {
        return Remedial::toggleAttendance($sessionId, $classId, $studentId);
    }

    public function createClass(array $data): array
    {
        $id = Remedial::createClass($data);
        if (!$id) {
            throw new \RuntimeException('ایجاد کلاس جبرانی امکان‌پذیر نیست', 500);
        }
        return ['id' => $id];
    }

    public function updateStudentTime(int $sessionId, int $studentId, string $field, string $value): void
    {
        Remedial::updateStudentTime($sessionId, $studentId, $field, $value);
    }

    public function deleteClass(int $id): void
    {
        Remedial::deleteClass($id);
    }
}