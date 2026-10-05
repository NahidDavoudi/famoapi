<?php

namespace App\Modules\Threads;

use App\Core\ApiException;


/**
 * Shared validation/normalization of a message payload (text + Telegram
 * attachment references). Used by student messages, supporter replies and
 * broadcasts.
 */
final class MessageContent
{
    public const MAX_ATTACHMENTS = 10;

    /**
     * @return array{0:?string,1:array<int,array>,2:?string} [body, attachments, media_group_id]
     */
    public static function parse(array $data): array
    {
        $body = isset($data['text']) ? trim((string) $data['text']) : '';
        $body = $body === '' ? null : $body;

        $attachments = self::normalizeAttachments($data['attachments'] ?? []);

        if ($body === null && $attachments === []) {
            throw new ApiException('متن یا پیوست پیام الزامی است', 422, 'EMPTY_MESSAGE');
        }

        $mediaGroupId = isset($data['media_group_id']) && $data['media_group_id'] !== ''
            ? (string) $data['media_group_id']
            : null;

        return [$body, $attachments, $mediaGroupId];
    }

    /**
     * @return array<int,array>
     */
    private static function normalizeAttachments(mixed $attachments): array
    {
        if ($attachments === null || $attachments === []) {
            return [];
        }
        if (!is_array($attachments)) {
            throw new ApiException('ساختار پیوست نامعتبر است', 422, 'INVALID_ATTACHMENT');
        }
        if (count($attachments) > self::MAX_ATTACHMENTS) {
            throw new ApiException('تعداد پیوست‌ها بیش از حد مجاز است', 422, 'TOO_MANY_ATTACHMENTS');
        }

        $normalized = [];
        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                throw new ApiException('ساختار پیوست نامعتبر است', 422, 'INVALID_ATTACHMENT');
            }
            $kind = (string) ($attachment['kind'] ?? '');
            $fileId = trim((string) ($attachment['tg_file_id'] ?? ''));
            if (!in_array($kind, MessageAttachment::KINDS, true) || $fileId === '') {
                throw new ApiException('پیوست نامعتبر است (نوع یا شناسه فایل)', 422, 'INVALID_ATTACHMENT');
            }

            $normalized[] = [
                'kind'       => $kind,
                'tg_file_id' => $fileId,
                'file_name'  => isset($attachment['file_name']) ? (string) $attachment['file_name'] : null,
                'mime_type'  => isset($attachment['mime_type']) ? (string) $attachment['mime_type'] : null,
                'file_size'  => isset($attachment['file_size']) ? (int) $attachment['file_size'] : null,
            ];
        }

        return $normalized;
    }
}
