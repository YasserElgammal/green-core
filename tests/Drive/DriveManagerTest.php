<?php

namespace YasserElgammal\Green\Tests\Drive;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Drive\DriveManager;
use YasserElgammal\Green\Drive\Drivers\FakeDriver;
use YasserElgammal\Green\Drive\Drivers\LocalDriver;
use YasserElgammal\Green\Drive\Exceptions\DiskNotFoundException;
use YasserElgammal\Green\Drive\Contracts\DriveDriverInterface;

class DriveManagerTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
            'default' => 'local',
            'disks' => [
                'local' => [
                    'driver' => 'local',
                    'root' => sys_get_temp_dir() . '/green_test_manager_' . uniqid(),
                ],
            ],
        ];
    }

    public function test_it_resolves_default_disk()
    {
        $manager = new DriveManager($this->config);
        $disk = $manager->disk();
        
        $this->assertInstanceOf(LocalDriver::class, $disk);
    }

    public function test_it_throws_for_unknown_disk()
    {
        $manager = new DriveManager($this->config);
        
        $this->expectException(DiskNotFoundException::class);
        $manager->disk('unknown');
    }

    public function test_it_caches_resolved_disks()
    {
        $manager = new DriveManager($this->config);
        $disk1 = $manager->disk('local');
        $disk2 = $manager->disk('local');
        
        $this->assertSame($disk1, $disk2);
    }

    public function test_it_supports_custom_creators()
    {
        $manager = new DriveManager([
            'disks' => [
                's3' => ['driver' => 'custom_s3']
            ]
        ]);
        
        $manager->extend('custom_s3', function($config) {
            return new FakeDriver(); // using FakeDriver as a dummy
        });
        
        $disk = $manager->disk('s3');
        $this->assertInstanceOf(FakeDriver::class, $disk);
    }

    public function test_it_can_swap_disk_with_fake()
    {
        $manager = new DriveManager($this->config);
        $fake = $manager->fake('local');
        
        $this->assertInstanceOf(FakeDriver::class, $fake);
        $this->assertSame($fake, $manager->disk('local'));
    }
}
