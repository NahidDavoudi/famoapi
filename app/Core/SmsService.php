<?php

namespace App\Core;

class SmsService
{
    private string $apiKey;
    private string $sender;

    public function __construct()
    {
        $this->apiKey = $_ENV['SMS_API_KEY'] ?? '';
        $this->sender = $_ENV['SMS_SENDER'] ?? '100001';
    }

    public function send(string $phone, string $message): bool
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        $url = "https://api.kavenegar.com/v1/{$this->apiKey}/sms/send.json";

        $postData = http_build_query([
            'receptor' => $phone,
            'sender'   => $this->sender,
            'message'  => $message,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("SmsService cURL error: {$error}");
            return false;
        }

        if ($httpCode !== 200) {
            error_log("SmsService HTTP {$httpCode}: {$response}");
            return false;
        }

        return true;
    }

    public function sendAppointmentReminder(string $phone, string $studentName, string $date, string $time): bool
    {
        $message = "سلام {$studentName} عزیز، نوبت شما در تاریخ {$date} ساعت {$time} در آموزشگاه فامو می‌باشد.";
        return $this->send($phone, $message);
    }

    public function sendAbsenceReminder(string $phone, string $studentName, string $parentName): bool
    {
        $message = "سلام {$parentName} عزیز، فرزند شما {$studentName} هنوز برای هفته جاری نوبت رزرو نکرده است. لطفاً هرچه سریعتر اقدام کنید.";
        return $this->send($phone, $message);
    }
}