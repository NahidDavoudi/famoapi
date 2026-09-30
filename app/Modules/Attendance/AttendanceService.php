<?php

namespace App\Modules\Attendance;

use App\Core\ApiException;
use App\Modules\Bot\IranDay;
use DateTimeImmutable;
use DateTimeZone;

class AttendanceService
{
    /**
     * @return array{date: string, date_jalali: string, field: ?string, entries: array}
     */
    public function listDay(?string $date, ?string $field): array
    {
        $date = $this->resolveDate($date);
        $field = ($field === null || $field === '') ? null : $field;

        return [
            'date'        => $date,
            'date_jalali' => IranDay::jalali($date),
            'field'       => $field,
            'entries'     => Attendance::listDay($date, $field),
        ];
    }

    /**
     * Same as listDay() but with the four *_at keys omitted entirely.
     *
     * @return array{date: string, date_jalali: string, field: ?string, entries: array}
     */
    public function printList(?string $date, ?string $field): array
    {
        $result = $this->listDay($date, $field);

        $result['entries'] = array_map(static function (array $entry): array {
            foreach (Attendance::TIME_COLUMNS as $key) {
                unset($entry[$key]);
            }
            return $entry;
        }, $result['entries']);

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function upsertEntry(array $data): array
    {
        $date = $this->resolveDate($data['date'] ?? null);
        $studentId = (int) ($data['student_id'] ?? 0);
        if ($studentId <= 0) {
            throw new ApiException('شناسه دانش‌آموز الزامی است', 422, 'VALIDATION_ERROR');
        }

        $status = $data['status'] ?? null;
        if ($status !== null && !in_array($status, ['present', 'absent'], true)) {
            throw new ApiException('وضعیت نامعتبر است', 422, 'VALIDATION_ERROR');
        }

        $clientUuid = $data['client_uuid'] ?? null;
        if ($clientUuid !== null && !is_string($clientUuid)) {
            throw new ApiException('شناسه یکتا نامعتبر است', 422, 'VALIDATION_ERROR');
        }

        return Attendance::upsertEntry(
            $date,
            $studentId,
            $status,
            (bool) ($data['removed'] ?? false),
            $clientUuid
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function setTime(int $id, string $field, mixed $value): array
    {
        if (!in_array($field, Attendance::TIME_COLUMNS, true)) {
            throw new ApiException('ستون زمان نامعتبر است', 422, 'VALIDATION_ERROR');
        }

        return Attendance::setTime($id, $field, $this->resolveTimeValue($value));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function addGuest(array $data): array
    {
        $date = $this->resolveDate($data['date'] ?? null);
        $guestName = trim((string) ($data['guest_name'] ?? ''));
        $field = trim((string) ($data['field'] ?? ''));

        if ($guestName === '') {
            throw new ApiException('نام مهمان الزامی است', 422, 'VALIDATION_ERROR');
        }
        if ($field === '') {
            throw new ApiException('رشته مهمان الزامی است', 422, 'VALIDATION_ERROR');
        }

        return Attendance::addGuest($date, $guestName, $field);
    }

    private function resolveDate(?string $date): string
    {
        if ($date === null || $date === '') {
            return IranDay::today();
        }

        if (!IranDay::isValidDate($date)) {
            throw new ApiException('تاریخ نامعتبر است', 422, 'VALIDATION_ERROR');
        }

        return $date;
    }

    private function resolveTimeValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value === 'now') {
            return IranDay::nowUtc();
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
