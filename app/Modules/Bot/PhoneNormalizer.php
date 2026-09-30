<?php

namespace App\Modules\Bot;

/**
 * Canonical Iranian mobile normalization shared by the Bot modules.
 *
 * Accepts Persian/Arabic digit forms and +98 / 0098 / 98 / 09 / 9 prefixes and
 * returns a single canonical form: 09XXXXXXXXX. Returns null when the input
 * cannot be normalized to a valid Iranian mobile number. This helper is kept
 * separate from Core\Validator on purpose (the existing validator is unchanged).
 */
final class PhoneNormalizer
{
    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    private const ARABIC_DIGITS  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    private const ASCII_DIGITS   = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /** Normalize any accepted representation to 09XXXXXXXXX, or null. */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        $value = str_replace(self::PERSIAN_DIGITS, self::ASCII_DIGITS, $value);
        $value = str_replace(self::ARABIC_DIGITS, self::ASCII_DIGITS, $value);
        // Drop spaces and common separators, plus bidi control characters.
        $value = str_replace([' ', '-', '.', '(', ')', '_'], '', $value);
        $value = (string) preg_replace('/[\x{200c}\x{200e}\x{200f}\x{202a}-\x{202e}]/u', '', $value);

        if (str_starts_with($value, '+98')) {
            $value = '0' . substr($value, 3);
        } elseif (str_starts_with($value, '0098')) {
            $value = '0' . substr($value, 4);
        } elseif (str_starts_with($value, '98') && strlen($value) === 12) {
            $value = '0' . substr($value, 2);
        } elseif (str_starts_with($value, '9') && strlen($value) === 10) {
            $value = '0' . $value;
        }

        $value = (string) preg_replace('/\D/', '', $value);

        return self::isCanonical($value) ? $value : null;
    }

    public static function isCanonical(string $value): bool
    {
        return (bool) preg_match('/^09\d{9}$/', $value);
    }

    /**
     * Common stored representations of a canonical number, used for indexed
     * lookups. Results are compared against the normalized value afterwards.
     *
     * @return string[]
     */
    public static function candidateForms(string $canonical): array
    {
        if (!self::isCanonical($canonical)) {
            return [$canonical];
        }

        $withoutZero = substr($canonical, 1);

        return array_values(array_unique([
            $canonical,
            $withoutZero,
            '+98' . $withoutZero,
            '0098' . $withoutZero,
            '98' . $withoutZero,
        ]));
    }
}
