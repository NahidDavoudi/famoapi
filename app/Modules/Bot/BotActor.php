<?php

namespace App\Modules\Bot;

use App\Core\ApiException;
use App\Modules\Admins\Admin;
use App\Modules\Students\Student;
use App\Modules\Supporters\Supporter;

/**
 * Resolves the account a bot request acts on behalf of, starting from the
 * stored Telegram chat link. All role and active-status decisions are made
 * here server-side; the bot's headers are only used to identify the link.
 */
final class BotActor
{
    public const ROLES = ['student', 'supporter', 'admin'];

    /**
     * @return array{link:array,role:string,account:array,account_id:int,name:string}
     */
    public static function resolve(string $role, int $telegramUserId, int $chatId): array
    {
        $role = strtolower(trim($role));
        if (!in_array($role, self::ROLES, true)) {
            throw new ApiException('نقش ربات نامعتبر است', 400, 'BOT_ROLE_INVALID');
        }

        // چت خصوصی: chat_id و telegram_user_id یکی هستن، ولی تو DB فقط chat_id ذخیره می‌شه.
        $link = TelegramLink::findByChatIdAndRole($chatId, $role);
        if (!$link) {
            throw new ApiException('این حساب تلگرام به کاربری متصل نیست', 401, 'BOT_NOT_LINKED');
        }

        // چک تطابق (احتیاطی؛ چون دقیقاً با chat_id جست‌وجو کردیم معمولاً همیشه پاس می‌شه)
        if ((string) $link['chat_id'] !== (string) $chatId) {
            throw new ApiException('شناسه گفتگو با اتصال ثبت‌شده مطابقت ندارد', 401, 'BOT_CHAT_MISMATCH');
        }

        $accountId = (int) $link['account_id'];

        if ($role === 'student') {
            $account = Student::findById($accountId);
            if (!$account || (int) ($account['is_active'] ?? 0) !== 1) {
                throw new ApiException('حساب کاربری فعال نیست', 403, 'ACCOUNT_INACTIVE');
            }
        } elseif ($role === 'supporter') {
            $account = Supporter::findById($accountId);
            if (!$account || !Supporter::isAccountActive($accountId)) {
                throw new ApiException('حساب کاربری فعال نیست', 403, 'ACCOUNT_INACTIVE');
            }
        } else {
            $account = Admin::findById($accountId);
            if (!$account || !Admin::isAccountActive($accountId)) {
                throw new ApiException('حساب کاربری فعال نیست', 403, 'ACCOUNT_INACTIVE');
            }
        }

        // یکدست‌سازی کلیدهای مورد نیاز بقیه کد
        $link['telegram_user_id'] = $telegramUserId;
        $link['is_blocked'] = 0;
        $link['linked_at'] = null;

        return [
            'link'       => $link,
            'role'       => $role,
            'account'    => $account,
            'account_id' => $accountId,
            'name'       => (string) ($account['name'] ?? $link['name'] ?? ''),
        ];
    }
}