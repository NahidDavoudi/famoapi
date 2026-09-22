<?php

namespace App\Modules\Auth;

use App\Core\Auth;
use App\Core\Database;
use App\Core\EmailService;

class AuthService
{
    private EmailService $mailer;

    public function __construct(?EmailService $mailer = null)
    {
        $this->mailer = $mailer ?? new EmailService();
    }

    public function login(string $username, string $password): array
    {
        $user = User::findByUsername($username);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new \RuntimeException('نام کاربری یا رمز عبور اشتباه است', 401);
        }

        $role = $user['role'];

        if ($role === 'admin' || $role === 'supporter') {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = gmdate('Y-m-d H:i:s', time() + 300); // 5 minutes

            $db = Database::getConnection();
            $stmt = $db->prepare(
                'INSERT INTO login_codes (user_id, code, expires_at) VALUES (?, ?, ?)'
            );
            $stmt->execute([$user['id'], $code, $expiresAt]);

            $email = $user['email'] ?? $user['username'] . '@famoacademy.ir';
            $this->mailer->sendVerificationCode($email, $user['username'], $code);

            return [
                'requires_2fa' => true,
                'user_id'      => (int) $user['id'],
                'username'     => $user['username'],
                'role'         => $role,
                'email_mask'   => $this->maskEmail($email),
            ];
        }

        $token = Auth::encode([
            'id'   => $user['id'],
            'role' => $role,
        ]);

        return [
            'token' => $token,
            'user'  => [
                'id'       => (int) $user['id'],
                'username' => $user['username'],
                'role'     => $role,
            ],
        ];
    }

    public function verify2fa(int $userId, string $code): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'SELECT * FROM login_codes
             WHERE user_id = ? AND code = ? AND used = 0 AND expires_at > UTC_TIMESTAMP()
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $code]);
        $record = $stmt->fetch();

        if (!$record) {
            throw new \RuntimeException('کد تأیید نامعتبر یا منقضی شده است', 401);
        }

        $stmt = $db->prepare('UPDATE login_codes SET used = 1 WHERE id = ?');
        $stmt->execute([$record['id']]);

        $user = User::findById($userId);
        if (!$user) {
            throw new \RuntimeException('کاربر یافت نشد', 404);
        }

        $token = Auth::encode([
            'id'   => $user['id'],
            'role' => $user['role'],
        ]);

        return [
            'token' => $token,
            'user'  => [
                'id'       => (int) $user['id'],
                'username' => $user['username'],
                'role'     => $user['role'],
            ],
        ];
    }

    public function register(array $data): array
    {
        if (User::findByUsername($data['phone'])) {
            throw new \RuntimeException('این شماره تلفن قبلاً ثبت‌نام کرده است', 409);
        }

        $userId = User::create([
            'username' => $data['phone'],
            'password' => $data['password'],
            'role'     => 'student',
        ]);

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO students (user_id, name, national_id, grade, field, phone)
             VALUES (:user_id, :name, :national_id, :grade, :field, :phone)'
        );
        $stmt->execute([
            'user_id'     => $userId,
            'name'        => $data['name'],
            'national_id' => $data['nationalId'],
            'grade'       => $data['grade'],
            'field'       => $data['field'],
            'phone'       => $data['phone'],
        ]);
        $studentId = (int) $db->lastInsertId();

        $db->prepare('UPDATE users SET linked_id = :linked_id WHERE id = :id')
           ->execute(['linked_id' => $studentId, 'id' => $userId]);

        $token = Auth::encode([
            'id'   => $userId,
            'role' => 'student',
        ]);

        return [
            'token' => $token,
            'user'  => [
                'id'       => $userId,
                'username' => $data['phone'],
                'role'     => 'student',
            ],
        ];
    }

    public function me(int $userId): array
    {
        $user = User::findById($userId);
        if (!$user) {
            throw new \RuntimeException('کاربر یافت نشد', 404);
        }
        return [
            'id'       => (int) $user['id'],
            'username' => $user['username'],
            'role'     => $user['role'],
        ];
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        $name = $parts[0] ?? '';
        $domain = $parts[1] ?? '';
        $masked = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 2));
        return $masked . '@' . $domain;
    }
}
