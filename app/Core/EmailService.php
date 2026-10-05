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

    private function smtpCommand($fp, string $command, int $expectedCode, string $context): bool
    {
        if ($command !== '') {
            fwrite($fp, $command . "\r\n");
        }
        $response = '';
        while (($line = fgets($fp, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr((string) $line, 0, 3);
        if ($code !== $expectedCode) {
            error_log("[EmailService] {$context} failed ({$code}): " . trim($response));
            return false;
        }
        return true;
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
        if (empty($this->smtpHost) || empty($this->smtpUser) || empty($this->smtpPass)) {
            error_log("[EmailService] SMTP not fully configured. Would send to: $to, subject: $subject");
            return false;
        }

        $remote = ($this->smtpPort === 465 ? 'ssl://' : 'tcp://') . $this->smtpHost . ':' . $this->smtpPort;
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
        if (!$fp) {
            error_log("[EmailService] Connection failed: $errstr ($errno)");
            return false;
        }

        stream_set_timeout($fp, 15);

        // Read banner
        if (!$this->smtpCommand($fp, '', 220, 'banner')) { fclose($fp); return false; }

        // EHLO
        $domain = parse_url($this->fromEmail, PHP_URL_HOST) ?: 'localhost';
        if (!$this->smtpCommand($fp, "EHLO {$domain}", 250, 'EHLO')) { fclose($fp); return false; }

        // STARTTLS (ports 587/25)
        if ($this->smtpPort !== 465) {
            if (!$this->smtpCommand($fp, 'STARTTLS', 220, 'STARTTLS')) { fclose($fp); return false; }
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("[EmailService] TLS negotiation failed"); fclose($fp); return false;
            }
            // Re-EHLO after TLS
            if (!$this->smtpCommand($fp, "EHLO {$domain}", 250, 'EHLO-TLS')) { fclose($fp); return false; }
        }

        // AUTH LOGIN
        $b64User = base64_encode($this->smtpUser);
        $b64Pass = base64_encode($this->smtpPass);
        if (!$this->smtpCommand($fp, 'AUTH LOGIN', 334, 'AUTH')) { fclose($fp); return false; }
        if (!$this->smtpCommand($fp, $b64User, 334, 'username')) { fclose($fp); return false; }
        if (!$this->smtpCommand($fp, $b64Pass, 235, 'password')) { fclose($fp); return false; }

        // MAIL FROM
        if (!$this->smtpCommand($fp, "MAIL FROM:<{$this->fromEmail}>", 250, 'MAIL FROM')) { fclose($fp); return false; }

        // RCPT TO
        if (!$this->smtpCommand($fp, "RCPT TO:<{$to}>", 250, 'RCPT TO')) { fclose($fp); return false; }

        // DATA
        if (!$this->smtpCommand($fp, 'DATA', 354, 'DATA')) { fclose($fp); return false; }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/html; charset=UTF-8\r\n"
                 . "From: {$this->fromName} <{$this->fromEmail}>\r\n"
                 . "To: <{$to}>\r\n"
                 . "Subject: {$encodedSubject}\r\n"
                 . "X-Mailer: PHP/" . PHP_VERSION . "\r\n";

        fwrite($fp, $headers . "\r\n" . $body . "\r\n.");
        if (!$this->smtpCommand($fp, '', 250, 'body')) { fclose($fp); return false; }

        // QUIT
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    }
}