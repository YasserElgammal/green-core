<?php

namespace YasserElgammal\Green\Tests\Drive;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Drive\Drive;
use YasserElgammal\Green\Drive\DriveManager;
use YasserElgammal\Green\Drive\Drivers\FakeDriver;

class DriveTest extends TestCase
{
    private DriveManager $manager;
    private Drive $drive;

    protected function setUp(): void
    {
        $this->manager = new DriveManager([
            'default' => 'dummy',
            'disks' => [
                'dummy' => ['driver' => 'dummy_driver']
            ]
        ]);
        
        $this->manager->extend('dummy_driver', fn() => new FakeDriver());
        $this->drive = new Drive($this->manager);
    }

    public function test_it_delegates_to_default_disk()
    {
        $this->drive->put('a.txt', 'test');
        $this->assertTrue($this->drive->exists('a.txt'));
        $this->assertEquals('test', $this->drive->get('a.txt'));
        
        $this->drive->delete('a.txt');
        $this->assertFalse($this->drive->exists('a.txt'));
    }

    public function test_it_can_switch_disks()
    {
        $this->manager->extend('other_driver', fn() => new FakeDriver());
        $config = $this->manager->getConfig();
        $config['disks']['other'] = ['driver' => 'other_driver'];
        $manager = new DriveManager($config);
        $manager->extend('dummy_driver', fn() => new FakeDriver());
        $manager->extend('other_driver', fn() => new FakeDriver());
        
        $drive = new Drive($manager);
        
        $drive->put('1.txt', '1');
        $drive->disk('other')->put('2.txt', '2');
        
        $this->assertTrue($drive->exists('1.txt'));
        $this->assertFalse($drive->exists('2.txt'));
        
        $this->assertFalse($drive->disk('other')->exists('1.txt'));
        $this->assertTrue($drive->disk('other')->exists('2.txt'));
    }

    public function test_it_delegates_faking_to_manager()
    {
        $fake = $this->drive->fake();
        $this->drive->put('faked.txt', 'yes');
        
        $fake->assertExists('faked.txt');
    }
}
