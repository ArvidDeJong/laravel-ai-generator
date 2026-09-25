<?php

namespace Darvis\LaravelAiGenerator\Support;

use RuntimeException;

/**
 * Reads and updates the variables in a .env file for the install wizard, keeping every other line,
 * comment and the order as they are.
 *
 * @internal
 */
final class EnvFile
{
    public function __construct(
        private readonly string $path,
    ) {}

    /**
     * The value of a variable, or null when the file does not set it.
     */
    public function get(string $key): ?string
    {
        if (! is_file($this->path)) {
            return null;
        }

        $contents = (string) file_get_contents($this->path);

        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $match)) {
            return null;
        }

        $value = trim($match[1]);

        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
            $value = stripcslashes(substr($value, 1, -1));
        }

        return $value;
    }

    /**
     * Set the variables: a variable the file has is replaced where it stands, a new one is appended.
     *
     * @param  array<string, string|null>  $values  A null value is written as an empty value
     *
     * @throws RuntimeException When the file cannot be written
     */
    public function set(array $values): void
    {
        $contents = is_file($this->path) ? (string) file_get_contents($this->path) : '';
        $appended = [];

        foreach ($values as $key => $value) {
            $line = $key.'='.self::format($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $contents)) {
                $contents = (string) preg_replace_callback($pattern, fn () => $line, $contents, 1);
            } else {
                $appended[] = $line;
            }
        }

        if ($appended !== []) {
            $contents = rtrim($contents, "\n");
            $contents .= ($contents === '' ? '' : "\n\n").implode("\n", $appended)."\n";
        }

        if (@file_put_contents($this->path, $contents) === false) {
            throw new RuntimeException("Could not write to {$this->path}.");
        }
    }

    /**
     * Quote a value when the .env parser would otherwise read it differently.
     */
    private static function format(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (preg_match('/[\s#"\'\\\\$]/', $value)) {
            return '"'.addcslashes($value, '"\\$').'"';
        }

        return $value;
    }
}
