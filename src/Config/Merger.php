<?php

namespace YasserElgammal\Green\Config;

final class Merger
{
    public static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (!array_key_exists($key, $base)) {
                $base[$key] = $value;
                continue;
            }

            if (is_array($base[$key]) && is_array($value) && !array_is_list($base[$key]) && !array_is_list($value)) {
                $base[$key] = self::merge($base[$key], $value);
                continue;
            }

            // Lists are configuration values, not maps: an override replaces the list.
            $base[$key] = $value;
        }

        return $base;
    }
}
