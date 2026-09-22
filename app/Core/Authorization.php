<?php

namespace App\Core;

use Psr\Http\Message\ServerRequestInterface;

class Authorization
{
    public static function requireRole(string $role, ServerRequestInterface $request): void
    {
        $user = $request->getAttribute('user');

        if (!$user) {
            throw new \RuntimeException('Unauthenticated', 401);
        }

        $userRole = $user->role ?? '';

        if ($role === 'admin' && $userRole !== 'admin') {
            throw new \RuntimeException('دسترسی محدود به مدیران', 403);
        }

        if ($role === 'supporter' && !in_array($userRole, ['admin', 'supporter'])) {
            throw new \RuntimeException('دسترسی محدود به پشتیبانان', 403);
        }

        if ($role === 'student' && $userRole !== 'student') {
            throw new \RuntimeException('دسترسی محدود به دانش‌آموزان', 403);
        }
    }
}