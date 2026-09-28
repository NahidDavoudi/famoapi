<?php

namespace App\Core;

use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Ownership guard for student-scoped resources.
 *
 * Admin/supporter tokens keep their original behaviour (they may pass any
 * student_id). Student tokens are always pinned to the student id embedded
 * in their JWT (`student_id` claim); requesting another student's data is a
 * 403.
 */
class StudentScope
{
    /**
     * Resolve the effective student id for the current request.
     *
     * @param Request  $request   Current request (carries the decoded JWT user).
     * @param int|null $requested Student id supplied by the client (query/body/path).
     * @param bool     $required  Whether a student id must be present for non-student roles.
     */
    public static function studentId(Request $request, ?int $requested, bool $required = false): ?int
    {
        $requested = ($requested === 0) ? null : $requested;

        if (self::role($request) === 'student') {
            $selfId = self::selfId($request);
            if ($selfId <= 0) {
                throw new \RuntimeException('حساب کاربری به دانش‌آموزی متصل نیست', 403);
            }
            if ($requested !== null && $requested !== $selfId) {
                throw new \RuntimeException('دسترسی غیرمجاز', 403);
            }
            return $selfId;
        }

        if ($requested === null && $required) {
            throw new \RuntimeException('شناسه دانش‌آموز الزامی است', 400);
        }

        return $requested;
    }

    /**
     * The student id linked to the current token (0 when not a student).
     */
    public static function selfId(Request $request): int
    {
        $user = $request->getAttribute('user');
        if (!is_object($user) || !isset($user->student_id)) {
            return 0;
        }
        return (int) $user->student_id;
    }

    public static function role(Request $request): string
    {
        $user = $request->getAttribute('user');
        return is_object($user) ? (string) ($user->role ?? '') : '';
    }

    public static function isStudent(Request $request): bool
    {
        return self::role($request) === 'student';
    }
}
