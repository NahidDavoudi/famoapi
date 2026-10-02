<?php
declare(strict_types=1);

namespace App\Core;

final class RateLimitedException extends ApiException
{
    public function __construct(private int $retryAfter, string $message = 'تعداد تلاش‌ها بیش از حد مجاز است. لطفاً بعداً تلاش کنید.')
    {
        parent::__construct($message, 429, 'RATE_LIMITED');
    }

    public function getRetryAfter(): int
    {
        return max(1, $this->retryAfter);
    }
}
