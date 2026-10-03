<?php

namespace App\Modules\Bot;

use App\Core\ResponseHelper;
use App\Core\ApiException;
use App\Core\Database;
use App\Modules\Auth\User;
use App\Modules\Students\StudentService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Module 1 (Bot Gateway) endpoints. Service-key protected; `me` also requires
 * a resolved acting account.
 */
class BotController
{
    public function __construct(private StudentService $students)
    {
    }

    public function ping(Request $request, Response $response): Response
    {
        return ResponseHelper::json($response, [
            'status'        => 'ok',
            'utc_time'      => IranDay::nowUtc(),
            'iran_date'     => IranDay::today(),
            'iran_date_jalali' => IranDay::jalali(),
        ]);
    }

    public function me(Request $request, Response $response): Response
    {
        /** @var array $actor */
        $actor = $request->getAttribute('bot_actor');

        return ResponseHelper::json($response, [
            'role'       => $actor['role'],
            'account_id' => $actor['account_id'],
            'name'       => $actor['name'],
            'telegram_user_id' => (int) $actor['link']['telegram_user_id'],
            'chat_id'    => (int) $actor['link']['chat_id'],
            'is_blocked' => (int) $actor['link']['is_blocked'] === 1,
            'linked_at'  => $actor['link']['linked_at'],
        ]);
    }

    public function resolve(Request $request, Response $response): Response
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $chatId = $this->chatId($response, $body['chat_id'] ?? null);
        if ($chatId instanceof Response) {
            return $chatId;
        }

        $user = User::findActiveByChatId($chatId);

        return ResponseHelper::json($response, ['user' => $this->publicUser($user)]);
    }

    public function linkPhone(Request $request, Response $response): Response
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $chatId = $this->chatId($response, $body['chat_id'] ?? null);
        if ($chatId instanceof Response) {
            return $chatId;
        }

        if (!is_string($body['phone'] ?? null)) {
            return ResponseHelper::error($response, 'VALIDATION_ERROR', 'phone باید رشته باشد', 422);
        }
        try {
            $phone = $this->students->normalizePhone($body['phone']);
        } catch (ApiException $e) {
            return ResponseHelper::error($response, $e->getErrorCode(), $e->getMessage(), $e->getHttpStatus());
        }

        $user = User::findActiveByUsername($phone);
        if ($user === null) {
            return ResponseHelper::json($response, ['user' => null]);
        }

        $chatOwner = User::findAnyByChatId($chatId);
        if ($chatOwner !== null && (int) $chatOwner['id'] !== (int) $user['id']) {
            return ResponseHelper::error($response, 'CHAT_ALREADY_LINKED', 'این چت‌آیدی قبلاً به حساب دیگری متصل شده است', 409);
        }
        if (($user['chat_id'] ?? null) !== null && (string) $user['chat_id'] !== '' && (string) $user['chat_id'] !== $chatId) {
            return ResponseHelper::error($response, 'USER_ALREADY_LINKED', 'این حساب قبلاً به چت‌آیدی دیگری متصل شده است', 409);
        }

        try {
            User::linkChatId((int) $user['id'], $chatId);
        } catch (\PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                return ResponseHelper::error($response, 'CHAT_ALREADY_LINKED', 'این چت‌آیدی قبلاً به حساب دیگری متصل شده است', 409);
            }
            throw $e;
        }

        $user['chat_id'] = $chatId;

        return ResponseHelper::json($response, ['user' => $this->publicUser($user)]);
    }

    public function register(Request $request, Response $response): Response
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $chatId = $this->chatId($response, $body['chat_id'] ?? null);
        if ($chatId instanceof Response) {
            return $chatId;
        }

        if (!is_string($body['full_name'] ?? null) || !is_string($body['major'] ?? null) || !is_string($body['phone'] ?? null)) {
            return ResponseHelper::error($response, 'VALIDATION_ERROR', 'نام، شماره و رشته باید رشته باشند', 422);
        }
        $fullName = trim($body['full_name']);
        $grade = filter_var($body['grade'] ?? null, FILTER_VALIDATE_INT);
        $major = strtolower(trim($body['major']));
        $majorFields = [
            'tajrobi' => 'تجربی',
            'riazi' => 'ریاضی',
            'ensani' => 'انسانی',
            'rahnamayi' => 'راهنمایی',
        ];

        if ($fullName === '' || $grade === false || $grade < 7 || $grade > 12 || !isset($majorFields[$major])) {
            return ResponseHelper::error($response, 'VALIDATION_ERROR', 'نام، پایه ۷ تا ۱۲ و رشته معتبر الزامی است', 422);
        }

        try {
            $phone = $this->students->normalizePhone($body['phone']);
        } catch (ApiException $e) {
            return ResponseHelper::error($response, $e->getErrorCode(), $e->getMessage(), $e->getHttpStatus());
        }

        if ($this->students->phoneIsRegistered($phone)) {
            return ResponseHelper::error($response, 'PHONE_ALREADY_REGISTERED', 'این شماره تلفن قبلاً ثبت شده است', 409);
        }
        if (User::findAnyByChatId($chatId) !== null) {
            return ResponseHelper::error($response, 'CHAT_ALREADY_LINKED', 'این چت‌آیدی قبلاً به حساب دیگری متصل شده است', 409);
        }

        $field = (int) $grade <= 9 ? 'راهنمایی' : $majorFields[$major];
        $password = bin2hex(random_bytes(16));
        $db = Database::getConnection();
        $startedTransaction = !$db->inTransaction();
        if ($startedTransaction) {
            $db->beginTransaction();
        }

        try {
            $this->students->create([
                'name' => $fullName,
                'full_name' => $fullName,
                'phone' => $phone,
                'grade' => (int) $grade,
                'field' => $field,
                'national_id' => null,
                'password' => $password,
                'chat_id' => $chatId,
            ]);
            $user = User::findActiveByChatId($chatId);
            if ($user === null) {
                throw new \RuntimeException('ثبت کاربر ربات کامل نشد');
            }
            if ($startedTransaction) {
                $db->commit();
            }
        } catch (\PDOException $e) {
            if ($startedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            if ((string) $e->getCode() === '23000') {
                $code = $this->students->phoneIsRegistered($phone) ? 'PHONE_ALREADY_REGISTERED' : 'CHAT_ALREADY_LINKED';
                return ResponseHelper::error($response, $code, 'شماره یا چت‌آیدی قبلاً ثبت شده است', 409);
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($startedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return ResponseHelper::json($response, ['user' => $this->publicUser($user)], null, 201);
    }

    private function chatId(Response $response, mixed $value): string|Response
    {
        $chatId = trim((string) ($value ?? ''));
        if ((!is_string($value) && !is_int($value)) || $chatId === '' || !preg_match('/^-?\d{1,20}$/', $chatId)) {
            return ResponseHelper::error($response, 'VALIDATION_ERROR', 'chat_id معتبر نیست', 422);
        }

        return $chatId;
    }

    private function publicUser(?array $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'role' => (string) $user['role'],
            'full_name' => $user['full_name'] ?? null,
            'username' => (string) $user['username'],
            'chat_id' => $user['chat_id'] ?? null,
            'supporter_id' => isset($user['supporter_id']) ? (int) $user['supporter_id'] : null,
            'linked_id' => isset($user['linked_id']) ? (int) $user['linked_id'] : null,
        ];
    }
}
