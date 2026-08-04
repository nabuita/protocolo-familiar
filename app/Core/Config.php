<?php

declare(strict_types=1);

namespace App\Core;

final readonly class Config
{
    /** @param array<string, string> $values */
    private function __construct(private array $values) {}

    public static function load(string $root): self
    {
        $values = [];
        $envFile = $root . '/.env';
        if (is_file($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $values[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
            }
        }

        foreach ($_ENV + $_SERVER as $key => $value) {
            if (is_string($key) && is_string($value) && $value !== '') {
                $values[$key] = $value;
            }
        }

        $values['ROOT'] = $root;

        return new self($values);
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->values[$key] ?? $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = strtolower($this->get($key, $default ? 'true' : 'false'));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}
