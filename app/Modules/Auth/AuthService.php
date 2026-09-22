<?php

namespace App\Modules\Auth;

use App\Core\Auth;
use App\Core\Database;

class AuthService
{
    public function login(string $username, string $password): array
    {
        $user = User::findByUsername($username);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new \RuntimeException('نام کاربری یا رمز عبور اشتباه است', 401);
        }

        $token = Auth::encode([
            'id'   => $user['id'],
            'role' => $user['role'],
        ]);

        return [
            'token' => $token,
            'user'  => [
                'id'       => $user['id'],
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
            'password' => $data['phone'],
            'role'     => 'student',
        ]);

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO students (user_id, name, national_id, grade, field, phone) VALUES (:user_id, :name, :national_id, :grade, :field, :phone)'
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
            'id'       => $user['id'],
            'username' => $user['username'],
            'role'     => $user['role'],
        ];
    }
}