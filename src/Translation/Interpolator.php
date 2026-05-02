<?php

namespace YasserElgammal\Green\Translation;

/**
 * Variable substitution engine for translated strings.
 *
 * Supports two syntaxes:
 *   - Colon prefix:  "Welcome, :name!"
 *   - Brace-wrapped: "Welcome, {name}!"
 *
 * Replacements are applied in a single pass to avoid
 * double-substitution issues when a replacement value
 * itself contains a placeholder-like substring.
 */
final class Interpolator
{
    /**
     * Replace placeholders in $message with values from $replacements.
     *
     * @param string                     $message      The raw translated string.
     * @param array<string, string|int|float> $replacements Key-value pairs for substitution.
     *
     * @return string The interpolated string.
     */
    public function interpolate(string $message, array $replacements): string
    {
        if ($replacements === []) {
            return $message;
        }

        $map = [];

        foreach ($replacements as $placeholder => $value) {
            $stringValue = $this->castToString($value);

            // Normalize: accept keys with or without the leading colon / braces.
            $bare = ltrim($placeholder, ':');
            $bare = trim($bare, '{}');

            // :placeholder syntax
            $map[':' . $bare]       = $stringValue;
            // {placeholder} syntax
            $map['{' . $bare . '}'] = $stringValue;
        }

        return strtr($message, $map);
    }

    /**
     * Safely cast a replacement value to string.
     */
    private function castToString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }
}
