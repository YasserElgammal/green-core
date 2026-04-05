<?php

namespace YasserElgammal\Green\Notifications\Messages;

class MailMessage
{
    public string $subject = '';
    public string $template = '';
    public array $data = [];

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function template(string $template, array $data = []): static
    {
        $this->template = $template;
        $this->data = $data;
        return $this;
    }
}
