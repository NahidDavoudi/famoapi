<?php

namespace App\Modules\Auth;

use App\Core\Auth;
use App\Core\Database;
use App\Core\SmsService;

class AuthService
{
    private SmsService $sms;

    public function __construct(?SmsService $sms = null)
    {
        $this->sms = $sms ?? new SmsService();
    }

    /**
     * Linked student id, only meaningful for the student role.
     */
    private function studentIdFor(array $user): ?int
    {
        if (($user['role'] ?? '') !== 'student') {
            return null;
        }
        $id = (int) ($user['linked_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    public function login(string $username, string $password): array
    {
        $user = User::findByUsername($username);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new \RuntimeException('نام کاربری یا رمز عبور اشتباه است', 401);
        }

        $role = $user['role'];

        if ($role === 'admin' || $role === 'supporter') {
            // TODO: برای فعال‌سازی روی سایر نقش‌ها (مثلاً student)، این شرط را گسترش دهید
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = gmdate('Y-m-d H:i:s', time() + 300); // 5 minutes

            $db = Database::getConnection();
            $stmt = $db->prepare(
                'INSERT INTO login_codes (user_id, code, expires_at) VALUES (?, ?, ?)'
            );
            $stmt->execute([$user['id'], $code, $expiresAt]);

            $mobile = $user['username'];
            $sent = $this->sms->sendVerificationCode($mobile, $code);

            if (!$sent) {
                error_log('[' . date('Y-m-d H:i:s') . '] AuthService: Failed to send 2FA code to user ' . $user['id'] . PHP_EOL, 3, __DIR__ . '/../../../storage/logs/app.log');
                throw new \RuntimeException('ارسال کد تأیید با مشکل مواجه شد. لطفاً دقایقی بعد تلاش کنید.', 500);
            }

            return [
                'requires_2fa' => true,
                'user_id'      => (int) $user['id'],
                'username'     => $user['username'],
                'role'         => $role,
                'phone_mask'   => substr($mobile, 0, 4) . '***' . substr($mobile, -2),
            ];
        }

        $studentId = $this->studentIdFor($user);

        $token = Auth::encode([
            'id'         => $user['id'],
            'role'       => $role,
            'student_id' => $studentId,
        ]);

        return [
            'token' => $token,
            'user'  => [
                'id'         => (int) $user['id'],
                'username'   => $user['username'],
                'role'       => $role,
                'student_id' => $studentId,
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

        $studentId = $this->studentIdFor($user);

        $token = Auth::encode([
            'id'         => $user['id'],
            'role'       => $user['role'],
            'student_id' => $studentId,
        ]);

        return [
            'token' => $token,
            'user'  => [
                'id'         => (int) $user['id'],
                'username'   => $user['username'],
                'role'       => $user['role'],
                'student_id' => $studentId,
            ],
        ];
    }

    public function register(array $data): array
    {
        if (User::findByUsername($data['phone'])) {
            throw new \RuntimeException('این شماره تلفن قبلاً ثبت‌نام کرده است', 409);
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            // Some legacy imports have no AUTO_INCREMENT on these tables.
            // Allocate IDs explicitly until the schema migration is applied.
            $userId = (int) $db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM users')->fetchColumn();
            User::create([
                'id'        => $userId,
                'full_name' => $data['name'],
                'username'  => $data['phone'],
                'password'  => $data['password'],
                'role'      => 'student',
            ]);

            // The legacy students table links back through users.linked_id.
            $stmt = $db->prepare(
                'INSERT INTO students (id, name, national_id, grade, field, phone)
                 VALUES (:id, :name, :national_id, :grade, :field, :phone)'
            );
            $studentId = (int) $db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM students')->fetchColumn();
            $stmt->execute([
                'id'          => $studentId,
                'name'        => $data['name'],
                'national_id' => $data['nationalId'],
                'grade'       => $data['grade'],
                'field'       => $data['field'],
                'phone'       => $data['phone'],
            ]);
            $db->prepare('UPDATE users SET linked_id = :linked_id WHERE id = :id')
               ->execute(['linked_id' => $studentId, 'id' => $userId]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $token = Auth::encode([
            'id'         => $userId,
            'role'       => 'student',
            'student_id' => $studentId,
        ]);

        return [
            'token' => $token,
            'user'  => [
                'id'         => $userId,
                'username'   => $data['phone'],
                'role'       => 'student',
                'student_id' => $studentId,
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
            'id'         => (int) $user['id'],
            'username'   => $user['username'],
            'role'       => $user['role'],
            'full_name'  => $user['full_name'] ?? null,
            'student_id' => $this->studentIdFor($user),
        ];
    }
}
