<?php

namespace YasserElgammal\Green\Tests\Drive;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Drive\Drivers\LocalDriver;
use YasserElgammal\Green\Drive\Exceptions\FileNotFoundException;

class LocalDriverTest extends TestCase
{
    private string $tempDir;
    private LocalDriver $driver;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/green_test_local_' . uniqid();
        @mkdir($this->tempDir, 0777, true);
        
        $this->driver = new LocalDriver(['root' => $this->tempDir]);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            is_dir("$dir/$file") ? $this->removeDir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function test_it_can_put_and_get_files()
    {
        $this->driver->put('test.txt', 'hello world');
        
        $this->assertTrue($this->driver->exists('test.txt'));
        $this->assertEquals('hello world', $this->driver->get('test.txt'));
    }

    public function test_it_creates_parent_directories_automatically()
    {
        $this->driver->put('a/b/c/test.txt', 'nested');
        
        $this->assertTrue($this->driver->exists('a/b/c/test.txt'));
        $this->assertTrue(is_dir($this->tempDir . '/a/b/c'));
    }

    public function test_it_can_delete_files()
    {
        $this->driver->put('test.txt', 'data');
        $this->assertTrue($this->driver->exists('test.txt'));
        
        $this->driver->delete('test.txt');
        $this->assertFalse($this->driver->exists('test.txt'));
    }

    public function test_it_throws_when_getting_nonexistent_file()
    {
        $this->expectException(FileNotFoundException::class);
        $this->driver->get('missing.txt');
    }

    public function test_it_can_get_size_and_last_modified()
    {
        $this->driver->put('test.txt', '12345');
        
        $this->assertEquals(5, $this->driver->size('test.txt'));
        $this->assertGreaterThan(0, $this->driver->lastModified('test.txt'));
    }

    public function test_it_can_copy_and_move_files()
    {
        $this->driver->put('source.txt', 'data');
        
        $this->driver->copy('source.txt', 'copied.txt');
        $this->assertTrue($this->driver->exists('source.txt'));
        $this->assertTrue($this->driver->exists('copied.txt'));
        $this->assertEquals('data', $this->driver->get('copied.txt'));
        
        $this->driver->move('source.txt', 'moved.txt');
        $this->assertFalse($this->driver->exists('source.txt'));
        $this->assertTrue($this->driver->exists('moved.txt'));
        $this->assertEquals('data', $this->driver->get('moved.txt'));
    }

    public function test_it_lists_files_and_directories()
    {
        $this->driver->put('root.txt', 'a');
        $this->driver->put('dir/file1.txt', 'b');
        $this->driver->put('dir/file2.txt', 'c');
        $this->driver->makeDirectory('empty_dir');
        
        $rootFiles = $this->driver->files();
        $this->assertEquals(['root.txt'], $rootFiles);
        
        $dirFiles = $this->driver->files('dir');
        $this->assertEquals(['dir/file1.txt', 'dir/file2.txt'], $dirFiles);
        
        $dirs = $this->driver->directories();
        $this->assertEquals(['dir', 'empty_dir'], $dirs);
    }

    public function test_it_handles_streams()
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'stream data');
        rewind($stream);
        
        $this->driver->writeStream('stream.txt', $stream);
        fclose($stream);
        
        $this->assertEquals('stream data', $this->driver->get('stream.txt'));
        
        $readStream = $this->driver->readStream('stream.txt');
        $this->assertEquals('stream data', stream_get_contents($readStream));
        fclose($readStream);
    }
}
