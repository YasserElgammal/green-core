<?php

namespace YasserElgammal\Green\Tests\Drive;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Drive\PathValidator;
use YasserElgammal\Green\Drive\Exceptions\InvalidPathException;

class PathValidatorTest extends TestCase
{
    private string $tempDir;
    private PathValidator $validator;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/green_test_' . uniqid();
        @mkdir($this->tempDir, 0777, true);
        $this->validator = new PathValidator($this->tempDir);
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

    public function test_it_creates_root_if_missing()
    {
        $dir = $this->tempDir . '/new_root';
        $this->assertDirectoryDoesNotExist($dir);

        new PathValidator($dir);

        $this->assertDirectoryExists($dir);
    }

    public function test_it_normalizes_paths()
    {
        $path = $this->validator->resolve('a//b/./c\d//e');
        
        $expected = str_replace('\\', '/', $this->tempDir . '/a/b/c/d/e');
        $this->assertEquals($expected, str_replace('\\', '/', $path));
    }

    public function test_it_resolves_root_directory()
    {
        $path1 = $this->validator->resolveDirectory('');
        $path2 = $this->validator->resolveDirectory('.');
        $path3 = $this->validator->resolveDirectory('/');

        $expected = str_replace('\\', '/', $this->tempDir);
        $this->assertEquals($expected, str_replace('\\', '/', $path1));
        $this->assertEquals($expected, str_replace('\\', '/', $path2));
        $this->assertEquals($expected, str_replace('\\', '/', $path3));
    }

    public function test_it_rejects_path_traversal()
    {
        $this->expectException(InvalidPathException::class);
        $this->expectExceptionMessage('path traversal detected');

        $this->validator->resolve('a/../../b');
    }

    public function test_it_rejects_null_bytes()
    {
        $this->expectException(InvalidPathException::class);
        $this->expectExceptionMessage('null byte detected');

        $this->validator->resolve("a/\0/b");
    }

    public function test_it_rejects_control_characters()
    {
        $this->expectException(InvalidPathException::class);
        $this->expectExceptionMessage('control character detected');

        $this->validator->resolve("a/\x08/b");
    }

    public function test_it_rejects_root_escape_via_symlink()
    {
        // Create a symlink outside the root
        $outsideFile = sys_get_temp_dir() . '/outside.txt';
        file_put_contents($outsideFile, 'test');

        $symlinkPath = $this->tempDir . '/link.txt';
        // Only run symlink test if symlinks are supported
        if (@symlink($outsideFile, $symlinkPath)) {
            $this->expectException(InvalidPathException::class);
            $this->expectExceptionMessage('path escapes storage root');

            try {
                $this->validator->resolve('link.txt');
            } finally {
                @unlink($outsideFile);
            }
        } else {
            @unlink($outsideFile);
            $this->markTestSkipped('Symlinks not supported on this system');
        }
    }
}
