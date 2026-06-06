<?php

namespace YasserElgammal\Green\Tests\Connect;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Connect\ConnectManager;

class ConnectManagerTest extends TestCase
{
    public function test_it_uses_a_default_connection_without_config(): void
    {
        $manager = new ConnectManager();

        $this->assertSame('default', $manager->getDefaultConnection());
        $this->assertSame([
            'driver' => 'symfony',
            'base_url' => '',
            'timeout' => 10,
            'connect_timeout' => 5,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ], $manager->getConnectionConfig('default'));
    }
}
