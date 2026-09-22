<?php

namespace App\Core;

class EmailService
{
    private string $smtpHost;
    private int $smtpPort;
    private string $smtpUser;
    private string $smtpPass;
    private string $fromEmail;
    private string $fromName;

    public function __construct()
    {
        $this->smtpHost = $_ENV['SMTP_HOST'] ?? '';
        $this->smtpPort = (int) ($_ENV['SMTP_PORT'] ?? 587);
        $this->smtpUser = $_ENV['SMTP_USER'] ?? '';
        $this->smtpPass = $_ENV['SMTP_PASS'] ?? '';
        $this->fromEmail = $_ENV['SMTP_FROM'] ?? 'noreply@famoacademy.ir';
        $this->fromName = $_ENV['SMTP_FROM_NAME'] ?? 'Famo Academy';
    }

    public function send(string $to, string $subject, string $body): bool
    {
        return $this->sendSMTP($to, $subject, $body);
    }

    public function sendVerificationCode(string $to, string $name, string $code): bool
    {
        $subject = 'کد تأیید ورود به پنل مدیریت - فامو';
        $body = "
        <div dir='rtl' style='font-family:Tahoma;max-width:500px;margin:auto;padding:20px;border:1px solid #e2d9c6;border-radius:8px;'>
            <h2 style='color:#445d84;'>فامو آکادمی</h2>
            <p>{$name} عزیز،</p>
            <p>کد تأیید ورود شما:</p>
            <div style='text-align:center;padding:20px;font-size:32px;font-weight:bold;letter-spacing:8px;color:#445d84;direction:ltr;'>{$code}</div>
            <p>این کد تا ۵ دقیقه معتبر است.</p>
            <p style='color:#8b786d;font-size:12px;'>اگر شما درخواست ورود نداده‌اید، این پیام را نادیده بگیرید.</p>
        </div>";
        return $this->send($to, $subject, $body);
    }

    private function sendSMTP(string $to, string $subject, string $body): bool
    {
        if (empty($this->smtpHost)) {
            error_log("[EmailService] SMTP not configured. Would send to: $to, subject: $subject");
            return false;
        }

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";

        if ($this->smtpPort === 25 || $this->smtpPort === 587 || $this->smtpPort === 465) {
            ini_set('SMTP', $this->smtpHost);
            ini_set('smtp_port', $this->smtpPort);
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $success = @mail($to, $encodedSubject, $body, $headers);
        if (!$success) {
            error_log("[EmailService] Failed to send email to: $to, subject: $subject");
        }
        return $success;
    }
}