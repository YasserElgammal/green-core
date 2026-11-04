<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Mail\Transports;

use Countable;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class ArrayTransport extends AbstractTransport implements Countable
{
    /** @var array<int, SentMessage> */
    private array $messages = [];

    protected function doSend(SentMessage $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * @return array<int, SentMessage>
     */
    public function sent(): array
    {
        return $this->messages;
    }

    /**
     * @return array<int, Email>
     */
    public function emails(): array
    {
        $emails = [];
        foreach ($this->messages as $sent) {
            $msg = $sent->getOriginalMessage();
            if ($msg instanceof Email) {
                $emails[] = $msg;
            }
        }
        return $emails;
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function clear(): void
    {
        $this->messages = [];
    }

    public function hasSentTo(string $address): bool
    {
        foreach ($this->emails() as $email) {
            foreach ($email->getTo() as $to) {
                if ($to->getAddress() === $address) {
                    return true;
                }
            }
        }
        return false;
    }

    public function __toString(): string
    {
        return 'array://';
    }
}
