<?php

namespace App\Modules\Bot;

use App\Core\ApiException;
use App\Core\ResponseHelper;
use App\Modules\Auth\User;
use App\Modules\Students\Student;
use App\Modules\Students\StudentService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Module 1 (Bot Gateway) endpoints. Service-key protected; `me` also requires
 * a resolved acting account.
 *
 * Contract used by the Telegram bot (FamoApi.php):
 *   POST /bot/resolve    {chat_id}                                   -> data.user | 404
 *   POST /bot/link-phone {chat_id, phone}                            -> data.user (null if phone unknown)
 *   POST /bot/register   {chat_id, phone, full_name, grade, major}   -> data.user (201)
 * Error codes: VALIDATION_ERROR, CHAT_ALREADY_LINKED, USER_ALREADY_LINKED, PHONE_ALREADY_REGISTERED
 */
class BotController
{
    private const MAJORS = ['rahnamayi', 'tajrobi', 'riazi', 'ensani'];

    public function ping(Request $request, Response $response): Response
    {
        return ResponseHelper::json($response, [
            'status'           => 'ok',
            'utc_time'         => IranDay::nowUtc(),
            'iran_date'        => IranDay::today(),
            'iran_date_jalali' => IranDay::jalali(),
        ]);
    }

    public function me(Request $request, Response $response): Response
    {
        /** @var array $actor */
        $actor = $request->getAttribute('bot_actor');

        return ResponseHelper::json($response, [
            'role'             => $actor['role'],
            'account_id'       => $actor['account_id'],
            'name'             => $actor['name'],
            'telegram_user_id' => (int) $actor['link']['telegram_user_id'],
            'chat_id'          => (int) $actor['link']['chat_id'],
            'is_blocked'       => (int) $actor['link']['is_blocked'] === 1,
            'linked_at'        => $actor['link']['linked_at'],
        ]);
    }

    /** POST /bot/resolve */
    public function resolve(Request $request, Response $response): Response
    {
        $chatId = $this->chatId($this->body($request));

        $accounts = TelegramLink::findByChatId($chatId);
        if (!$accounts) {
            throw new ApiException('این چت به حسابی وصل نیست', 404, 'NOT_LINKED');
        }

        return ResponseHelper::json($response, [
            'user' => $this->userPayload($accounts[0], $chatId),
        ]);
    }

    /** POST /bot/link-phone */
    public function linkPhone(Request $request, Response $response): Response
    {
        $body   = $this->body($request);
        $chatId = $this->chatId($body);
        $phone  = $this->phone($body);

        $user = User::findByUsername($phone);
        if (!$user) {
            // Not an error: the bot continues with the signup flow.
            return ResponseHelper::json($response, ['user' => null]);
        }

        $linkedHere = TelegramLink::findByChatId($chatId);
        if ($linkedHere && !$this->containsUsername($linkedHere, $phone)) {
            throw new ApiException('این چت قبلاً به حساب دیگری وصل شده است', 409, 'CHAT_ALREADY_LINKED');
        }

        if (!empty($user['chat_id']) && (int) $user['chat_id'] !== $chatId) {
            throw new ApiException('این حساب قبلاً به تلگرام دیگری وصل شده است', 409, 'USER_ALREADY_LINKED');
        }

        TelegramLink::attachChatId((int) $user['id'], $chatId);

        return ResponseHelper::json($response, [
            'user' => $this->userPayload($user, $chatId),
        ]);
    }

    /** POST /bot/register */
    public function register(Request $request, Response $response): Response
    {
        $body     = $this->body($request);
        $chatId   = $this->chatId($body);
        $phone    = $this->phone($body);
        $fullName = trim((string) ($body['full_name'] ?? ''));
        $grade    = (int) ($body['grade'] ?? 0);
        $major    = (string) ($body['major'] ?? '');

        if (mb_strlen($fullName) < 2 || $grade < 7 || $grade > 12 || !in_array($major, self::MAJORS, true)) {
            throw new ApiException('اطلاعات ثبت‌نام نامعتبر است', 422, 'VALIDATION_ERROR');
        }
        if ($grade <= 9) {
            $major = 'rahnamayi';
        }

        if (User::findByUsername($phone)) {
            throw new ApiException('این شماره قبلاً ثبت شده است', 409, 'PHONE_ALREADY_REGISTERED');
        }
        if (TelegramLink::findByChatId($chatId)) {
            throw new ApiException('این چت قبلاً به حسابی وصل شده است', 409, 'CHAT_ALREADY_LINKED');
        }

        // Creates the student AND its users row (username = phone) in one transaction.
        (new StudentService())->create([
            'name'        => $fullName,
            'grade'       => $grade,
            'field'       => $major,
            'phone'       => $phone,
            'national_id' => null,
        ]);

        $user = User::findByUsername($phone);
        if (!$user) {
            throw new ApiException('ساخت حساب ناموفق بود', 500, 'REGISTER_FAILED');
        }

        TelegramLink::attachChatId((int) $user['id'], $chatId);

        return ResponseHelper::json($response, [
            'user' => $this->userPayload($user, $chatId),
        ], 201);
    }

    // ---------------------------------------------------------------- helpers

    private function body(Request $request): array
    {
        $body = $request->getParsedBody();
        return is_array($body) ? $body : [];
    }

    private function chatId(array $body): int
    {
        $chatId = (int) ($body['chat_id'] ?? 0);
        if ($chatId === 0) {
            throw new ApiException('chat_id الزامی است', 422, 'VALIDATION_ERROR');
        }
        return $chatId;
    }

    private function phone(array $body): string
    {
        $phone = $this->normalizePhone((string) ($body['phone'] ?? ''));
        if (!preg_match('/^09\d{9}$/', $phone)) {
            throw new ApiException('شماره موبایل نامعتبر است', 422, 'VALIDATION_ERROR');
        }
        return $phone;
    }

    /** Persian/Arabic digits -> Latin; +98 / 0098 / 98 / 9xxxxxxxxx -> 09xxxxxxxxx */
    private function normalizePhone(string $raw): string
    {
        $raw = strtr($raw, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $d = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($d, '0098')) {
            $d = '0' . substr($d, 4);
        } elseif (str_starts_with($d, '98') && strlen($d) === 12) {
            $d = '0' . substr($d, 2);
        } elseif (strlen($d) === 10 && $d[0] === '9') {
            $d = '0' . $d;
        }
        return $d;
    }

    private function containsUsername(array $accounts, string $username): bool
    {
        foreach ($accounts as $a) {
            if (($a['username'] ?? null) === $username) {
                return true;
            }
        }
        return false;
    }

    /** Shape expected by FamoApi::userAsLink(). */
    private function userPayload(array $user, int $chatId): array
    {
        $name = $user['full_name'] ?? null;
        if (!$name && ($user['role'] ?? '') === 'student') {
            $student = Student::findById((int) $user['linked_id']);
            $name = $student['name'] ?? '';
        }

        return [
            'id'        => (int) ($user['id'] ?? 0),
            'role'      => (string) $user['role'],
            'linked_id' => (int) $user['linked_id'],
            'full_name' => (string) $name,
            'chat_id'   => $chatId,
        ];
    }
}