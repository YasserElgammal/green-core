<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Scheduling;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use YasserElgammal\Green\Queue\Contracts\JobInterface;

final class ScheduledTask
{
    public const TYPE_CALLBACK = 'callback';
    public const TYPE_COMMAND = 'command';
    public const TYPE_JOB = 'job';

    private string $expression = '* * * * *';
    private ?DateTimeZone $timezone = null;
    private ?string $description = null;
    private ?string $name = null;
    private bool $withoutOverlapping = false;
    private int $overlapExpiresAfterSeconds = 3600;

    /** @var list<Closure(): bool> */
    private array $whenPredicates = [];

    /** @var list<Closure(): bool> */
    private array $skipPredicates = [];

    private function __construct(
        private readonly string $type,
        private readonly ?Closure $callback = null,
        private readonly ?string $command = null,
        /** @var array<string, mixed> */
        private readonly array $commandArguments = [],
        private readonly JobInterface|string|null $job = null,
        private readonly string $queue = 'default',
        private readonly ?string $queueConnection = null,
        ?string $defaultName = null,
    ) {
        $this->name = $defaultName;
    }

    public static function forCallback(callable $callback, ?string $name = null): self
    {
        return new self(
            type: self::TYPE_CALLBACK,
            callback: Closure::fromCallable($callback),
            defaultName: $name,
        );
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public static function forCommand(string $command, array $arguments = []): self
    {
        $command = trim($command);
        if ($command === '') {
            throw new InvalidArgumentException('Scheduled command name cannot be empty.');
        }

        return new self(
            type: self::TYPE_COMMAND,
            command: $command,
            commandArguments: $arguments,
            defaultName: $command,
        );
    }

    public static function forJob(
        JobInterface|string $job,
        string $queue = 'default',
        ?string $connection = null,
    ): self {
        if (is_string($job) && trim($job) === '') {
            throw new InvalidArgumentException('Scheduled job class cannot be empty.');
        }

        $defaultName = is_object($job) ? $job::class : $job;

        return new self(
            type: self::TYPE_JOB,
            job: $job,
            queue: $queue,
            queueConnection: $connection,
            defaultName: $defaultName,
        );
    }

    // ── Schedule Frequency ───────────────────────────────────────────────────

    public function cron(string $expression): self
    {
        // Validate eagerly so misconfigured schedules fail fast.
        new CronExpression($expression);
        $this->expression = trim($expression);

        return $this;
    }

    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    public function hourlyAt(int $minute): self
    {
        if ($minute < 0 || $minute > 59) {
            throw new InvalidArgumentException("Minute [{$minute}] must be between 0 and 59.");
        }

        return $this->cron("{$minute} * * * *");
    }

    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(string $time): self
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $matches)) {
            throw new InvalidArgumentException("Time [{$time}] must be in HH:MM format.");
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            throw new InvalidArgumentException("Time [{$time}] is out of valid 24-hour bounds.");
        }

        return $this->cron("{$minute} {$hour} * * *");
    }

    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    // ── Execution Controls ───────────────────────────────────────────────────

    public function timezone(string|DateTimeZone $timezone): self
    {
        $this->timezone = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;

        return $this;
    }

    public function when(callable $predicate): self
    {
        $this->whenPredicates[] = Closure::fromCallable($predicate);

        return $this;
    }

    public function skip(callable $predicate): self
    {
        $this->skipPredicates[] = Closure::fromCallable($predicate);

        return $this;
    }

    public function withoutOverlapping(int $expiresAfterSeconds = 3600): self
    {
        if ($expiresAfterSeconds < 1) {
            throw new InvalidArgumentException('Overlap expiration must be at least 1 second.');
        }

        $this->withoutOverlapping = true;
        $this->overlapExpiresAfterSeconds = $expiresAfterSeconds;

        return $this;
    }

    public function name(string $name): self
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Scheduled task name cannot be empty.');
        }

        $this->name = $trimmed;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    // ── Evaluation & Introspection ───────────────────────────────────────────

    public function isDue(DateTimeImmutable $now): bool
    {
        $localized = $this->timezone !== null ? $now->setTimezone($this->timezone) : $now;

        return (new CronExpression($this->expression))->isDue($localized);
    }

    public function nextRunDate(DateTimeImmutable $from): DateTimeImmutable
    {
        $localized = $this->timezone !== null ? $from->setTimezone($this->timezone) : $from;

        return (new CronExpression($this->expression))->nextRunDate($localized);
    }

    public function shouldRun(): bool
    {
        foreach ($this->whenPredicates as $predicate) {
            if (!$predicate()) {
                return false;
            }
        }

        foreach ($this->skipPredicates as $predicate) {
            if ($predicate()) {
                return false;
            }
        }

        return true;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function nameValue(): string
    {
        if ($this->name !== null && $this->name !== '') {
            return $this->name;
        }

        return match ($this->type) {
            self::TYPE_COMMAND => (string) $this->command,
            self::TYPE_JOB => is_object($this->job) ? $this->job::class : (string) $this->job,
            default => 'callback',
        };
    }

    public function expression(): string
    {
        return $this->expression;
    }

    public function timezoneName(): string
    {
        return $this->timezone?->getName() ?? date_default_timezone_get();
    }

    public function descriptionValue(): ?string
    {
        return $this->description;
    }

    public function preventsOverlapping(): bool
    {
        return $this->withoutOverlapping;
    }

    public function overlapExpiresAfterSeconds(): int
    {
        return $this->overlapExpiresAfterSeconds;
    }

    public function lockKey(): string
    {
        return sha1($this->type . '|' . $this->nameValue() . '|' . $this->expression);
    }

    public function callback(): ?Closure
    {
        return $this->callback;
    }

    public function command(): ?string
    {
        return $this->command;
    }

    /**
     * @return array<string, mixed>
     */
    public function commandArguments(): array
    {
        return $this->commandArguments;
    }

    public function job(): JobInterface|string|null
    {
        return $this->job;
    }

    public function queue(): string
    {
        return $this->queue;
    }

    public function queueConnection(): ?string
    {
        return $this->queueConnection;
    }
}
