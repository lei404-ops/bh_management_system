<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Tiny input validator (same messages the front end already expects: one string per field).
 *   $v = new Validator($request->all());
 *   $v->required('title', 'Title')->maxLength('title', 150, 'Title');
 *   if ($v->fails()) { ... $v->errors() ... }
 *   $clean = $v->get('title');   // trimmed string
 */
final class Validator
{
    private array $errors = [];

    public function __construct(private array $data) {}

    public function get(string $key, string $default = ''): string
    {
        $v = $this->data[$key] ?? $default;
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function required(string $key, string $label): self
    {
        if ($this->get($key) === '') {
            $this->errors[$key] = "$label is required.";
        }
        return $this;
    }

    public function maxLength(string $key, int $max, string $label): self
    {
        if (!isset($this->errors[$key]) && mb_strlen($this->get($key)) > $max) {
            $this->errors[$key] = "$label must be at most $max characters.";
        }
        return $this;
    }

    public function minLength(string $key, int $min, string $label): self
    {
        if (!isset($this->errors[$key]) && mb_strlen($this->get($key)) < $min) {
            $this->errors[$key] = "$label must be at least $min characters.";
        }
        return $this;
    }

    public function email(string $key, string $label): self
    {
        if (!isset($this->errors[$key]) && filter_var($this->get($key), FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$key] = "$label must be a valid email address.";
        }
        return $this;
    }

    public function inList(string $key, array $allowed, string $label): self
    {
        if (!isset($this->errors[$key]) && !in_array($this->get($key), $allowed, true)) {
            $this->errors[$key] = "$label has an invalid value.";
        }
        return $this;
    }

    public function positiveInt(string $key, string $label): self
    {
        if (!isset($this->errors[$key]) && filter_var($this->get($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->errors[$key] = "$label must be a positive whole number.";
        }
        return $this;
    }

    public function money(string $key, string $label): self
    {
        $v = $this->get($key);
        if (!isset($this->errors[$key]) && (!is_numeric($v) || (float) $v <= 0)) {
            $this->errors[$key] = "$label must be an amount greater than zero.";
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
