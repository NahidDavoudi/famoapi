<?php

namespace App\Modules\Linking;

use App\Core\ApiException;
use App\Modules\Bot\BotActor;
use App\Modules\Bot\PhoneNormalizer;
use App\Modules\Bot\TelegramLink;
use App\Modules\Students\Student;
use App\Modules\Supporters\Supporter;

/**
 * Module 2: Telegram identity & linking. The bot host proves ownership of a
 * shared contact; the API only stores the resulting link and enforces the
 * uniqueness rules (one account per role, one link per role per Telegram user).
 */
class LinkService
{
    public function lookup(string $rawPhone, ?int $telegramUserId): array
    {
        $canonical = PhoneNormalizer::normalize($rawPhone);
        if ($canonical === null) {
            throw new ApiException('شماره تلفن نامعتبر است', 422, 'INVALID_PHONE');
        }

        $forms = PhoneNormalizer::candidateForms($canonical);
        $identities = [];

        foreach (Student::findByPhoneForms($forms) as $student) {
            if (PhoneNormalizer::normalize($student['phone']) !== $canonical) {
                continue;
            }
            $identities[] = $this->identity(
                'student',
                (int) $student['id'],
                (string) $student['name'],
                (int) $student['is_active'] === 1,
                $telegramUserId
            );
        }

        foreach (Supporter::findByPhoneForms($forms) as $supporter) {
            if (PhoneNormalizer::normalize($supporter['phone']) !== $canonical) {
                continue;
            }
            $identities[] = $this->identity(
                'supporter',
                (int) $supporter['id'],
                (string) $supporter['name'],
                Supporter::isAccountActive((int) $supporter['id']),
                $telegramUserId
            );
        }

        return [
            'phone'      => $canonical,
            'identities' => $identities,
        ];
    }

    public function link(array $data): array
    {
        $role = strtolower(trim((string) ($data['role'] ?? '')));
        $accountId = (int) ($data['account_id'] ?? 0);
        $telegramUserId = (int) ($data['telegram_user_id'] ?? 0);
        $chatId = (int) ($data['chat_id'] ?? 0);

        if (!in_array($role, BotActor::ROLES, true)) {
            throw new ApiException('نقش نامعتبر است', 422, 'INVALID_ROLE');
        }
        if ($telegramUserId <= 0) {
            throw new ApiException('شناسه کاربر تلگرام نامعتبر است', 422, 'INVALID_TELEGRAM_USER');
        }
        if ($chatId <= 0) {
            throw new ApiException('شناسه گفتگوی تلگرام نامعتبر است', 422, 'INVALID_CHAT_ID');
        }
        if (empty($data['contact_verified'])) {
            throw new ApiException('تأیید مالکیت مخاطب توسط ربات انجام نشده است', 422, 'CONTACT_NOT_VERIFIED');
        }

        return $this->linkVerified($role, $accountId, $telegramUserId, $chatId);
    }

    public function linkVerified(string $role, int $accountId, int $telegramUserId, int $chatId): array
    {
        $account = $this->loadAccount($role, $accountId);
        if (!$account['is_active']) {
            throw new ApiException('حساب کاربری فعال نیست', 403, 'ACCOUNT_INACTIVE');
        }

        $existingByTelegram = TelegramLink::findByTelegramUserAndRole($telegramUserId, $role);
        if ($existingByTelegram && (int) $existingByTelegram['account_id'] !== $accountId) {
            throw new ApiException('این حساب تلگرام قبلاً به کاربر دیگری در همین نقش متصل شده است', 409, 'ALREADY_LINKED_ROLE');
        }

        $existingByAccount = TelegramLink::findByRoleAndAccount($role, $accountId);
        if ($existingByAccount && (int) $existingByAccount['telegram_user_id'] !== $telegramUserId) {
            throw new ApiException('این کاربر قبلاً به حساب تلگرام دیگری متصل شده است', 409, 'ACCOUNT_ALREADY_LINKED');
        }

        if ($existingByTelegram) {
            if ((int) $existingByTelegram['chat_id'] !== $chatId) {
                TelegramLink::updateChatId((int) $existingByTelegram['id'], $chatId);
            }
            $linkId = (int) $existingByTelegram['id'];
        } else {
            $linkId = TelegramLink::create($telegramUserId, $chatId, $role, $accountId);
        }

        return $this->linkPayload($linkId);
    }

    public function resolve(?int $telegramUserId, ?int $chatId, ?string $role): array
    {
        $role = $role !== null ? strtolower(trim($role)) : null;
        if ($role !== null && !in_array($role, BotActor::ROLES, true)) {
            throw new ApiException('نقش نامعتبر است', 422, 'INVALID_ROLE');
        }

        if ($telegramUserId !== null && $telegramUserId > 0) {
            $links = $this->linksForTelegramUser($telegramUserId, $role);
        } elseif ($chatId !== null && $chatId > 0) {
            $links = TelegramLink::findByChatId($chatId);
            if ($role !== null) {
                $links = array_values(array_filter($links, static fn ($l) => $l['role'] === $role));
            }
        } else {
            throw new ApiException('شناسه کاربر یا گفتگو الزامی است', 422, 'MISSING_IDENTIFIER');
        }

        $resolved = array_map(fn (array $link) => $this->linkPayload((int) $link['id']), $links);

        return [
            'links' => $resolved,
        ];
    }

    public function unlink(?int $telegramUserId, ?string $role, ?int $accountId): array
    {
        $role = $role !== null ? strtolower(trim($role)) : null;
        if ($role !== null && !in_array($role, BotActor::ROLES, true)) {
            throw new ApiException('نقش نامعتبر است', 422, 'INVALID_ROLE');
        }

        $deleted = 0;

        if ($telegramUserId !== null && $telegramUserId > 0) {
            if ($role === null) {
                foreach (BotActor::ROLES as $candidate) {
                    $deleted += TelegramLink::deleteByTelegramUserAndRole($telegramUserId, $candidate);
                }
            } else {
                $deleted += TelegramLink::deleteByTelegramUserAndRole($telegramUserId, $role);
            }
        } elseif ($role !== null && $accountId !== null && $accountId > 0) {
            $link = TelegramLink::findByRoleAndAccount($role, $accountId);
            if ($link) {
                TelegramLink::deleteById((int) $link['id']);
                $deleted = 1;
            }
        } else {
            throw new ApiException('شناسه کاربر یا حساب الزامی است', 422, 'MISSING_IDENTIFIER');
        }

        return ['unlinked' => $deleted];
    }

    public function setBlocked(bool $blocked, ?int $telegramUserId, ?int $chatId, ?string $role): array
    {
        $role = $role !== null ? strtolower(trim($role)) : null;
        if ($role !== null && !in_array($role, BotActor::ROLES, true)) {
            throw new ApiException('نقش نامعتبر است', 422, 'INVALID_ROLE');
        }

        if ($telegramUserId !== null && $telegramUserId > 0) {
            $affected = TelegramLink::setBlockedByTelegramUser($telegramUserId, $blocked, $role);
        } elseif ($chatId !== null && $chatId > 0) {
            $affected = TelegramLink::setBlockedByChatId($chatId, $blocked);
        } else {
            throw new ApiException('شناسه کاربر یا گفتگو الزامی است', 422, 'MISSING_IDENTIFIER');
        }

        return ['blocked' => $blocked, 'affected' => $affected];
    }

    /**
     * @return array<int,array>
     */
    private function linksForTelegramUser(int $telegramUserId, ?string $role): array
    {
        if ($role !== null) {
            $link = TelegramLink::findByTelegramUserAndRole($telegramUserId, $role);

            return $link ? [$link] : [];
        }

        return TelegramLink::findByTelegramUser($telegramUserId);
    }

    /**
     * @return array{account:array,is_active:bool}
     */
    private function loadAccount(string $role, int $accountId): array
    {
        if ($accountId <= 0) {
            throw new ApiException('شناسه حساب الزامی است', 422, 'MISSING_ACCOUNT_ID');
        }

        if ($role === 'student') {
            $account = Student::findById($accountId);
            if (!$account) {
                throw new ApiException('دانش‌آموز یافت نشد', 404, 'ACCOUNT_NOT_FOUND');
            }

            return ['account' => $account, 'is_active' => (int) ($account['is_active'] ?? 0) === 1];
        }

        $account = Supporter::findById($accountId);
        if (!$account) {
            throw new ApiException('پشتیبان یافت نشد', 404, 'ACCOUNT_NOT_FOUND');
        }

        return ['account' => $account, 'is_active' => Supporter::isAccountActive($accountId)];
    }

    private function identity(string $role, int $accountId, string $name, bool $isActive, ?int $telegramUserId): array
    {
        $link = TelegramLink::findByRoleAndAccount($role, $accountId);
        $linkedToThis = false;
        $linkedToOther = false;
        if ($link) {
            if ($telegramUserId !== null && (int) $link['telegram_user_id'] === $telegramUserId) {
                $linkedToThis = true;
            } else {
                $linkedToOther = true;
            }
        }

        return [
            'role'                     => $role,
            'account_id'               => $accountId,
            'name'                     => $name,
            'is_active'                => $isActive,
            'is_linked'                => $link !== null,
            'linked_to_this_telegram'  => $linkedToThis,
            'linked_to_other_telegram' => $linkedToOther,
        ];
    }

    private function linkPayload(int $linkId): array
    {
        $link = TelegramLink::findById($linkId);
        if (!$link) {
            throw new ApiException('اتصال یافت نشد', 404, 'NOT_FOUND');
        }

        $role = (string) $link['role'];
        $accountId = (int) $link['account_id'];
        $loaded = $this->loadAccount($role, $accountId);

        return [
            'link_id'          => (int) $link['id'],
            'role'             => $role,
            'account_id'       => $accountId,
            'name'             => (string) ($loaded['account']['name'] ?? ''),
            'is_active'        => $loaded['is_active'],
            'is_blocked'       => (int) $link['is_blocked'] === 1,
            'telegram_user_id' => (int) $link['telegram_user_id'],
            'chat_id'          => (int) $link['chat_id'],
            'linked_at'        => $link['linked_at'],
        ];
    }
}
