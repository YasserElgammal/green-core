<?php

namespace YasserElgammal\Green\Tests\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use YasserElgammal\Green\Console\Commands\CreateAuthorizerCommand;
use YasserElgammal\Green\Console\Commands\CreateListenerCommand;
use YasserElgammal\Green\Console\Commands\CreatePolicyCommand;

class CreateAuthAndSignalCommandsTest extends TestCase
{
    private string $projectRoot;
    private string $previousCwd;

    protected function setUp(): void
    {
        $this->previousCwd = getcwd() ?: '.';
        $this->projectRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'green-create-commands-' . uniqid();
        mkdir($this->projectRoot, 0777, true);
        chdir($this->projectRoot);
    }

    protected function tearDown(): void
    {
        chdir($this->previousCwd);
        $this->removeDirectory($this->projectRoot);
    }

    public function test_it_creates_policy(): void
    {
        $tester = new CommandTester(new CreatePolicyCommand());

        $exitCode = $tester->execute(['name' => 'Post']);
        $path = $this->projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Policies' . DIRECTORY_SEPARATOR . 'PostPolicy.php';

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($path);
        $this->assertStringContainsString('class PostPolicy extends Policy', file_get_contents($path));
    }

    public function test_it_creates_authorizer(): void
    {
        $tester = new CommandTester(new CreateAuthorizerCommand());

        $exitCode = $tester->execute(['name' => 'Post']);
        $path = $this->projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Authorizers' . DIRECTORY_SEPARATOR . 'PostAuthorizer.php';

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($path);
        $this->assertStringContainsString('class PostAuthorizer extends BaseAuthorizer', file_get_contents($path));
    }

    public function test_it_creates_listener(): void
    {
        $tester = new CommandTester(new CreateListenerCommand());

        $exitCode = $tester->execute(['name' => 'SendWelcomeEmail']);
        $path = $this->projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Listeners' . DIRECTORY_SEPARATOR . 'SendWelcomeEmail.php';

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($path);
        $this->assertStringContainsString('public function __invoke(array $payload): mixed', file_get_contents($path));
    }

    public function test_it_does_not_overwrite_existing_files(): void
    {
        $path = $this->projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Policies' . DIRECTORY_SEPARATOR . 'PostPolicy.php';
        mkdir(dirname($path), 0777, true);
        file_put_contents($path, '<?php // existing');

        $tester = new CommandTester(new CreatePolicyCommand());
        $exitCode = $tester->execute(['name' => 'Post']);

        $this->assertSame(1, $exitCode);
        $this->assertSame('<?php // existing', file_get_contents($path));
        $this->assertStringContainsString('already exists', $tester->getDisplay());
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($path);
    }
}
