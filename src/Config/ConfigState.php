<?php

namespace YasserElgammal\Green\Config;

enum ConfigState: string
{
    case Collecting = 'collecting';
    case Loaded = 'loaded';
    case Locked = 'locked';
}
