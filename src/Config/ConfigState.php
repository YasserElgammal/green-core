<?php

namespace YasserElgammal\Green\Config;

enum ConfigState: string
{
    case Unloaded = 'unloaded';
    case Ready = 'ready';
}
