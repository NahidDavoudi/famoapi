<?php

namespace App\Core;

class Validator
{
    private array $errors = [];

    public function required(string $field, string $label, mixed $value): self
    {
        if (empty($value) && $value !== '0' && $value !== 0) {
            $this->errors[] = "{$label} الزامی است";
        }
        return $this;
    }

    public function numeric(string $field, string $label, mixed $value): self
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->errors[] = "{$label} باید عددی باشد";
        }
        return $this;
    }

    public function phone(string $field, string $label, mixed $value): self
    {
        if ($value !== null && $value !== '' && !preg_match('/^09\d{9}$/', $value)) {
            $this->errors[] = "{$label} باید با 09 شروع شود و 11 رقم باشد";
        }
        return $this;
    }

    public function nationalId(string $field, string $label, mixed $value): self
    {
        if ($value !== null && $value !== '' && !preg_match('/^\d{10}$/', $value)) {
            $this->errors[] = "{$label} باید 10 رقم باشد";
        }
        return $this;
    }

    public function maxLength(string $field, string $label, mixed $value, int $max): self
    {
        if ($value !== null && mb_strlen($value) > $max) {
            $this->errors[] = "{$label} نمی‌تواند بیشتر از {$max} کاراکتر باشد";
        }
        return $this;
    }

    public function inArray(string $field, string $label, mixed $value, array $allowed): self
    {
        if ($value !== null && $value !== '' && !in_array($value, $allowed)) {
            $this->errors[] = "{$label} نامعتبر است";
        }
        return $this;
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): string
    {
        return $this->errors[0] ?? '';
    }
}