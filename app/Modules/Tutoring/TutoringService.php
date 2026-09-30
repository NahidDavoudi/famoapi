<?php

namespace App\Modules\Tutoring;

use App\Core\ApiException;
use App\Modules\Auth\User;
use App\Modules\Bot\IranDay;
use DateTimeImmutable;
use DateTimeZone;

class TutoringService
{
    public const STATUSES = ['absent', 'ready', 'break'];

    /**
     * @return array{classroom: array<string, mixed>, status: ?array<string, mixed>, open_session: ?array<string, mixed>}
     */
    public function me(object $user): array
    {
        $instructorId = $this->resolveInstructorId($user);
        $classroom = Tutoring::classroomForInstructor($instructorId);

        if ($classroom === null) {
            throw new ApiException('کلاس درس یافت نشد', 404, 'NOT_FOUND');
        }

        $status = Tutoring::currentStatus($instructorId, IranDay::today());

        return [
            'classroom'    => $classroom,
            'status'       => $status === null ? null : [
                'status'      => $status['status'],
                'started_at'  => $status['started_at'],
                'status_date' => $status['status_date'],
            ],
            'open_session' => Tutoring::openSession($instructorId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function setStatus(object $user, mixed $status): array
    {
        if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
            throw new ApiException('وضعیت نامعتبر است', 422, 'VALIDATION_ERROR');
        }

        return Tutoring::setStatus($this->resolveInstructorId($user), $status, IranDay::today());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function students(?string $query): array
    {
        return Tutoring::searchStudents(trim((string) $query));
    }

    /**
     * @param array<string, mixed> $data
     * @return array{session: array<string, mixed>, created: bool}
     */
    public function startSession(object $user, array $data): array
    {
        $instructorId = $this->resolveInstructorId($user);
        $classroom = Tutoring::classroomForInstructor($instructorId);

        if ($classroom === null) {
            throw new ApiException('کلاس درس یافت نشد', 404, 'NOT_FOUND');
        }

        $studentId = (int) ($data['student_id'] ?? 0);
        if ($studentId <= 0) {
            throw new ApiException('شناسه دانش‌آموز الزامی است', 422, 'VALIDATION_ERROR');
        }

        $clientUuid = $data['client_uuid'] ?? null;
        if ($clientUuid !== null && (!is_string($clientUuid) || $clientUuid === '')) {
            throw new ApiException('شناسه یکتا نامعتبر است', 422, 'VALIDATION_ERROR');
        }

        if ($clientUuid !== null) {
            $existing = Tutoring::findSessionByClientUuid($clientUuid);
            if ($existing !== null) {
                return ['session' => $existing, 'created' => false];
            }
        }

        $enteredAt = $this->normalizeTime($data['entered_at'] ?? null) ?? IranDay::nowUtc();

        $session = Tutoring::startSession(
            $classroom['id'],
            $studentId,
            IranDay::today(),
            $enteredAt,
            (int) ($user->sub ?? 0),
            $clientUuid
        );

        return ['session' => $session, 'created' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function endSession(object $user, int $sessionId, mixed $endedAt): array
    {
        $instructorId = $this->resolveInstructorId($user);
        $value = $this->normalizeTime($endedAt) ?? IranDay::nowUtc();

        return Tutoring::endSession($instructorId, $sessionId, $value);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function board(?string $since): array
    {
        $normalized = null;
        if ($since !== null && trim($since) !== '') {
            $normalized = $this->normalizeTime($since);
        }

        return Tutoring::board($normalized);
    }

    public function resolveInstructorId(object $user): int
    {
        $claim = $user->instructor_id ?? null;
        if ($claim !== null && (int) $claim > 0) {
            return (int) $claim;
        }

        $userId = (int) ($user->sub ?? 0);
        $record = $userId > 0 ? User::findById($userId) : null;
        if ($record !== null && ($record['role'] ?? null) === 'teacher' && (int) ($record['linked_id'] ?? 0) > 0) {
            return (int) $record['linked_id'];
        }

        throw new ApiException('دسترسی غیرمجاز', 403, 'FORBIDDEN');
    }

    private function normalizeTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new ApiException('مقدار زمان نامعتبر است', 422, 'VALIDATION_ERROR');
        }

        try {
            $moment = new DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new ApiException('مقدار زمان نامعتبر است', 422, 'VALIDATION_ERROR');
        }

        return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
