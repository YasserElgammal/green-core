<?php

namespace YasserElgammal\Green\Notifications\Channel;

use YasserElgammal\Green\Config\Typed\MailConfig;

class EmailChannel extends MailChannel
{
    public function __construct(MailConfig $config)
    {
        parent::__construct($config);
    }
}
