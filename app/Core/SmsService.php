<?php

namespace App\Core;

class SmsService
{
    private string $apiKey;
    private string $sender;
    private string $melipayamakUsername;
    private string $melipayamakPassword;
    private string $melipayamakBodyId;

    public function __construct()
    {
        $this->apiKey = $_ENV['SMS_API_KEY'] ?? '';
        $this->sender = $_ENV['SMS_SENDER'] ?? '100001';
        $this->melipayamakUsername = $_ENV['MELIPAYAMAK_USERNAME'] ?? '';
        $this->melipayamakPassword = $_ENV['MELIPAYAMAK_PASSWORD'] ?? '';
        $this->melipayamakBodyId = $_ENV['MELIPAYAMAK_PATTERN_BODY_ID'] ?? '';
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

    public function sendVerificationCode(string $mobile, string $code): bool
    {
        if (empty($this->melipayamakUsername) || empty($this->melipayamakPassword) || empty($this->melipayamakBodyId)) {
            error_log('[' . date('Y-m-d H:i:s') . '] SmsService: Melipayamak not fully configured.' . PHP_EOL, 3, __DIR__ . '/../../storage/logs/app.log');
            return false;
        }

        $mobile = preg_replace('/[^0-9]/', '', $mobile);
        if (!preg_match('/^09[0-9]{9}$/', $mobile)) {
            error_log('[' . date('Y-m-d H:i:s') . '] SmsService: Invalid mobile format for 2FA.' . PHP_EOL, 3, __DIR__ . '/../../storage/logs/app.log');
            return false;
        }

        $url = 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber';
        $jsonData = json_encode([
            'username' => $this->melipayamakUsername,
            'password' => $this->melipayamakPassword,
            'to'       => $mobile,
            'bodyId'   => (int) $this->melipayamakBodyId,
            'text'     => $code,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_POSTFIELDS     => $jsonData,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $logPath = __DIR__ . '/../../storage/logs/app.log';

        if ($curlError) {
            error_log('[' . date('Y-m-d H:i:s') . '] SmsService cURL error: ' . $curlError . PHP_EOL, 3, $logPath);
            return false;
        }

        if ($httpCode !== 200) {
            error_log('[' . date('Y-m-d H:i:s') . '] SmsService HTTP ' . $httpCode . PHP_EOL, 3, $logPath);
            return false;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || !isset($decoded['RetStatus']) || (int) $decoded['RetStatus'] !== 1) {
            $errMsg = is_array($decoded) ? ($decoded['StrRetStatus'] ?? 'unknown') : $response;
            error_log('[' . date('Y-m-d H:i:s') . '] SmsService Melipayamak rejected: ' . $errMsg . PHP_EOL, 3, $logPath);
            return false;
        }

        error_log('[' . date('Y-m-d H:i:s') . '] SmsService: Code sent to ' . substr($mobile, 0, 4) . '***' . PHP_EOL, 3, $logPath);
        return true;
    }
}