<?php
declare(strict_types=1);

namespace YasserElgammal\Green\Queue\Contracts;

interface JobInterface
{
    /** Execute the job's work. */
    public function handle(): void;

    /** Maximum number of attempts before the job is discarded. */
    public function maxAttempts(): int;

    /** Seconds to wait before retrying a failed attempt. */
    public function retryDelay(): int;
}
