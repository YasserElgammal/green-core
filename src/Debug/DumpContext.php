<?php

namespace YasserElgammal\Green\Debug;

final class DumpContext
{
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly int $memoryUsage,
        public readonly float $executionTime,
        public readonly string $sapi,
        public readonly string $timestamp,
    ) {
    }

    public static function capture(?array $caller = null): self
    {
        $caller ??= self::resolveCaller();
        $startedAt = self::startedAt();

        return new self(
            file: (string) ($caller['file'] ?? 'unknown'),
            line: (int) ($caller['line'] ?? 0),
            memoryUsage: memory_get_usage(true),
            executionTime: max(0, microtime(true) - $startedAt),
            sapi: PHP_SAPI,
            timestamp: date('Y-m-d H:i:s'),
        );
    }

    /**
     * @return array{file?: string, line?: int}
     */
    private static function resolveCaller(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);

        return $trace[1] ?? $trace[0] ?? [];
    }

    private static function startedAt(): float
    {
        $serverStart = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        if (is_numeric($serverStart)) {
            return (float) $serverStart;
        }

        $greenStart = $GLOBALS['__green_started_at'] ?? null;
        if (is_numeric($greenStart)) {
            return (float) $greenStart;
        }

        return microtime(true);
    }
}
