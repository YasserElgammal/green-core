<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Scheduling;

use DateTimeImmutable;
use DateTimeInterface;
use YasserElgammal\Green\Scheduling\Exceptions\InvalidCronExpressionException;

final class CronExpression
{
    /** @var array<int, true> */
    private readonly array $minutes;

    /** @var array<int, true> */
    private readonly array $hours;

    /** @var array<int, true> */
    private readonly array $daysOfMonth;

    /** @var array<int, true> */
    private readonly array $months;

    /** @var array<int, true> */
    private readonly array $daysOfWeek;

    private readonly bool $dayOfMonthWildcard;
    private readonly bool $dayOfWeekWildcard;

    public function __construct(private readonly string $expression)
    {
        $parts = preg_split('/\s+/', trim($expression));
        if (!is_array($parts) || count($parts) !== 5) {
            throw new InvalidCronExpressionException(
                "Cron expression [{$expression}] must have exactly 5 fields."
            );
        }

        $this->minutes = $this->parseField($parts[0], 0, 59, 'minute');
        $this->hours = $this->parseField($parts[1], 0, 23, 'hour');
        $this->daysOfMonth = $this->parseField($parts[2], 1, 31, 'day-of-month');
        $this->months = $this->parseField($parts[3], 1, 12, 'month');
        $this->daysOfWeek = $this->parseField($parts[4], 0, 7, 'day-of-week', normalizeSunday: true);

        $this->dayOfMonthWildcard = $parts[2] === '*';
        $this->dayOfWeekWildcard = $parts[4] === '*';
    }

    public function expression(): string
    {
        return $this->expression;
    }

    public function isDue(DateTimeInterface $dateTime): bool
    {
        $minute = (int) $dateTime->format('i');
        $hour = (int) $dateTime->format('G');
        $day = (int) $dateTime->format('j');
        $month = (int) $dateTime->format('n');
        $weekday = (int) $dateTime->format('w');

        if (!isset($this->minutes[$minute], $this->hours[$hour], $this->months[$month])) {
            return false;
        }

        $domMatch = isset($this->daysOfMonth[$day]);
        $dowMatch = isset($this->daysOfWeek[$weekday]);

        if ($this->dayOfMonthWildcard || $this->dayOfWeekWildcard) {
            return $domMatch && $dowMatch;
        }

        return $domMatch || $dowMatch;
    }

    public function nextRunDate(DateTimeInterface $from): DateTimeImmutable
    {
        $candidate = DateTimeImmutable::createFromInterface($from)
            ->setTime((int) $from->format('G'), (int) $from->format('i'), 0)
            ->modify('+1 minute');

        $limit = $candidate->modify('+4 years');

        while ($candidate <= $limit) {
            $month = (int) $candidate->format('n');
            if (!isset($this->months[$month])) {
                $candidate = $candidate->modify('first day of next month')->setTime(0, 0, 0);
                continue;
            }

            if (!$this->matchesDay($candidate)) {
                $candidate = $candidate->modify('+1 day')->setTime(0, 0, 0);
                continue;
            }

            $hour = (int) $candidate->format('G');
            if (!isset($this->hours[$hour])) {
                $nextHour = $candidate->modify('+1 hour');
                $candidate = $nextHour->setTime((int) $nextHour->format('G'), 0, 0);
                continue;
            }

            $minute = (int) $candidate->format('i');
            if (!isset($this->minutes[$minute])) {
                $candidate = $candidate->modify('+1 minute');
                continue;
            }

            return $candidate;
        }

        throw new InvalidCronExpressionException(
            "Unable to compute next run date for cron expression [{$this->expression}]."
        );
    }

    private function matchesDay(DateTimeImmutable $dateTime): bool
    {
        $domMatch = isset($this->daysOfMonth[(int) $dateTime->format('j')]);
        $dowMatch = isset($this->daysOfWeek[(int) $dateTime->format('w')]);

        if ($this->dayOfMonthWildcard || $this->dayOfWeekWildcard) {
            return $domMatch && $dowMatch;
        }

        return $domMatch || $dowMatch;
    }

    /**
     * @return array<int, true>
     */
    private function parseField(
        string $field,
        int $min,
        int $max,
        string $label,
        bool $normalizeSunday = false,
    ): array {
        $values = [];

        foreach (explode(',', $field) as $segment) {
            if ($segment === '') {
                throw new InvalidCronExpressionException(
                    "Invalid empty segment in cron {$label} field [{$field}]."
                );
            }

            $step = 1;
            if (str_contains($segment, '/')) {
                $stepParts = explode('/', $segment);
                if (count($stepParts) !== 2 || !ctype_digit($stepParts[1]) || (int) $stepParts[1] < 1) {
                    throw new InvalidCronExpressionException(
                        "Invalid step [{$segment}] in cron {$label} field [{$field}]."
                    );
                }
                $segment = $stepParts[0];
                $step = (int) $stepParts[1];
            }

            if ($segment === '*') {
                $start = $min;
                $end = $max;
            } elseif (str_contains($segment, '-')) {
                $rangeParts = explode('-', $segment);
                if (count($rangeParts) !== 2 || !ctype_digit($rangeParts[0]) || !ctype_digit($rangeParts[1])) {
                    throw new InvalidCronExpressionException(
                        "Invalid range [{$segment}] in cron {$label} field [{$field}]."
                    );
                }
                $start = (int) $rangeParts[0];
                $end = (int) $rangeParts[1];
                if ($start > $end || $start < $min || $end > $max) {
                    throw new InvalidCronExpressionException(
                        "Out-of-bounds range [{$segment}] in cron {$label} field [{$field}]."
                    );
                }
            } elseif (ctype_digit($segment)) {
                $start = (int) $segment;
                $end = $step > 1 ? $max : $start;
                if ($start < $min || $start > $max) {
                    throw new InvalidCronExpressionException(
                        "Out-of-bounds value [{$segment}] in cron {$label} field [{$field}]."
                    );
                }
            } else {
                throw new InvalidCronExpressionException(
                    "Invalid token [{$segment}] in cron {$label} field [{$field}]."
                );
            }

            for ($i = $start; $i <= $end; $i += $step) {
                $normalized = ($normalizeSunday && $i === 7) ? 0 : $i;
                $values[$normalized] = true;
            }
        }

        return $values;
    }
}
