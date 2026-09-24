<?php

namespace App\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
    private static string $algorithm = 'HS256';

    public static function encode(array $user): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'famo-jwt-secret-change-in-production';
        $ttl = (int) ($_ENV['JWT_TTL'] ?? 86400);

        $payload = [
            'sub'        => $user['id'],
            'role'       => $user['role'],
            'student_id' => isset($user['student_id']) ? (int) $user['student_id'] : null,
            'iat'        => time(),
            'exp'        => time() + $ttl,
        ];

        return JWT::encode($payload, $secret, self::$algorithm);
    }

    public static function decode(string $token): object
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'famo-jwt-secret-change-in-production';
        return JWT::decode($token, new Key($secret, self::$algorithm));
    }
}