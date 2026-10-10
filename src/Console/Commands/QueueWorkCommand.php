<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Console\Commands;

use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Throwable;
use YasserElgammal\Green\Queue\Worker;

#[AsCommand(
    name: 'queue:work',
    description: 'Process jobs from a queue',
)]
final class QueueWorkCommand extends BaseCommand
{
    public function __construct(private readonly Worker $worker)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('queue', null, InputOption::VALUE_OPTIONAL, 'Queue name to process', 'default')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Queue connection to use')
            ->addOption('sleep', null, InputOption::VALUE_OPTIONAL, 'Seconds to wait when the queue is empty', '3')
            ->addOption('max-jobs', null, InputOption::VALUE_OPTIONAL, 'Stop after processing this many jobs; 0 means unlimited', '0');
    }

    protected function handle(): int
    {
        try {
            [$queue, $connection, $sleep, $maxJobs] = $this->validatedOptions();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::INVALID;
        }

        $connectionLabel = $connection ?? 'default';
        $this->info("Processing queue [{$queue}] on connection [{$connectionLabel}].");

        try {
            $this->worker->run($queue, $connection, $sleep, $maxJobs);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array{string, ?string, int, int}
     */
    private function validatedOptions(): array
    {
        $queue = (string) ($this->option('queue') ?? 'default');
        $connection = $this->option('connection');
        $connection = is_string($connection) && $connection !== '' ? $connection : null;
        $sleep = filter_var($this->option('sleep'), FILTER_VALIDATE_INT);
        $maxJobs = filter_var($this->option('max-jobs'), FILTER_VALIDATE_INT);

        if ($queue === '') {
            throw new InvalidArgumentException('The queue name cannot be empty.');
        }

        if ($sleep === false || $sleep < 0) {
            throw new InvalidArgumentException('The --sleep option must be a non-negative integer.');
        }

        if ($maxJobs === false || $maxJobs < 0) {
            throw new InvalidArgumentException('The --max-jobs option must be a non-negative integer.');
        }

        return [$queue, $connection, $sleep, $maxJobs];
    }
}
