<?php

namespace App\Modules\Appointments;

use App\Core\Pagination;
use App\Core\SmsService;

class AppointmentService
{
    public function __construct(private ?SmsService $sms = null)
    {
        $this->sms ??= new SmsService();
    }
    public function list(int $page, int $perPage): array
    {
        $total = Appointment::countAll();
        $pagination = Pagination::build($page, $perPage, $total);
        $items = Appointment::findAll($pagination['page'], $perPage);

        return [
            'items'      => $items,
            'pagination' => $pagination,
        ];
    }

    public function createOrUpdate(array $data): array
    {
        $existing = Appointment::findByStudentAndDate($data['student_id'], $data['appointment_date']);

        if ($existing) {
            Appointment::update($existing['id'], $data);
            $appointment = Appointment::findById($existing['id']);
        } else {
            $id = Appointment::create($data);
            $appointment = Appointment::findById($id);

            if (!empty($appointment['phone'])) {
                $this->sms->sendAppointmentReminder(
                    $appointment['phone'],
                    $appointment['student_name'],
                    $appointment['appointment_date'],
                    $appointment['start_time'] ?? ''
                );
            }
        }

        return $appointment;
    }

    public function updateStatus(int $id, string $status): array
    {
        $appointment = Appointment::findById($id);
        if (!$appointment) {
            throw new \RuntimeException('نوبت یافت نشد', 404);
        }

        Appointment::updateStatus($id, $status);
        return Appointment::findById($id);
    }

    public function delete(int $id): void
    {
        $appointment = Appointment::findById($id);
        if (!$appointment) {
            throw new \RuntimeException('نوبت یافت نشد', 404);
        }

        Appointment::delete($id);
    }
}