<?php

namespace YasserElgammal\Green\Tests\Debug;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use YasserElgammal\Green\Database\Model;
use YasserElgammal\Green\Debug\DebugConfig;
use YasserElgammal\Green\Debug\DumpContext;
use YasserElgammal\Green\Debug\Dumper;
use YasserElgammal\Green\Debug\Renderers\CliRenderer;
use YasserElgammal\Green\Debug\Renderers\HtmlRenderer;

class DumperTest extends TestCase
{
    public function test_debug_config_can_be_loaded_from_project_config_file(): void
    {
        $basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'green-leaf-config-' . uniqid();
        $configDir = $basePath . DIRECTORY_SEPARATOR . 'config';
        mkdir($configDir, 0777, true);
        file_put_contents($configDir . DIRECTORY_SEPARATOR . 'leaf.php', "<?php\nreturn ['max_depth' => 7, 'max_items' => 11, 'max_string_length' => 1234, 'dark_theme' => false];\n");

        $previous = getcwd();
        chdir($basePath);

        try {
            $config = DebugConfig::fromProjectConfig();
        } finally {
            chdir($previous);
            unlink($configDir . DIRECTORY_SEPARATOR . 'leaf.php');
            rmdir($configDir);
            rmdir($basePath);
        }

        $this->assertSame(7, $config->maxDepth);
        $this->assertSame(11, $config->maxItems);
        $this->assertSame(1234, $config->maxStringLength);
        $this->assertFalse($config->darkTheme);
    }

    public function test_it_dumps_scalars_and_arrays_with_item_limits(): void
    {
        $dumper = new Dumper(new DebugConfig(maxDepth: 5, maxItems: 2));

        $node = $dumper->dump(['name' => 'Green', 'enabled' => true, 'extra' => 123]);

        $this->assertSame('array', $node->type);
        $this->assertTrue($node->truncated);
        $this->assertSame(3, $node->meta['count']);
        $this->assertCount(2, $node->children);
        $this->assertSame('string', $node->children['name']->type);
        $this->assertSame('bool', $node->children['enabled']->type);
    }

    public function test_it_marks_repeated_objects_as_circular(): void
    {
        $object = new \stdClass();
        $object->self = $object;

        $node = (new Dumper(new DebugConfig(maxDepth: 5)))->dump($object);

        $this->assertSame('object', $node->type);
        $this->assertArrayHasKey('self', $node->children);
        $this->assertTrue($node->children['self']->circular);
    }

    public function test_it_respects_max_depth(): void
    {
        $node = (new Dumper(new DebugConfig(maxDepth: 2)))->dump(['a' => ['b' => ['c' => 'deep']]]);

        $this->assertTrue($node->children['a']->children['b']->truncated);
        $this->assertSame('Maximum depth reached', $node->children['a']->children['b']->meta['reason']);
    }

    public function test_it_dumps_models_and_exceptions(): void
    {
        $model = new LeafTestUser(['id' => 7, 'name' => 'Ada']);
        $modelNode = (new Dumper())->dump($model);

        $this->assertSame('model', $modelNode->type);
        $this->assertSame('users', $modelNode->meta['table']);
        $this->assertSame(7, $modelNode->meta['primary_key_value']);
        $this->assertSame('array', $modelNode->children['attributes']->type);

        $exceptionNode = (new Dumper())->dump(new RuntimeException('Broken leaf', 500));

        $this->assertSame('exception', $exceptionNode->type);
        $this->assertSame(RuntimeException::class, $exceptionNode->meta['class']);
        $this->assertSame('Broken leaf', $exceptionNode->children['message']->value);
    }

    public function test_renderers_include_context_and_values(): void
    {
        $config = new DebugConfig(maxDepth: 5, maxItems: 5);
        $node = (new Dumper($config))->dump(['leaf' => 'green']);
        $context = new DumpContext(__FILE__, __LINE__, 1024 * 1024, 0.0123, 'cli', '2026-06-06 12:00:00');

        $cli = (new CliRenderer())->render($node, $context, $config);
        $html = (new HtmlRenderer())->render($node, $context, $config);

        $this->assertStringContainsString('leaf()', $cli);
        $this->assertStringContainsString(__FILE__, $cli);
        $this->assertStringContainsString('green', $cli);

        $this->assertStringContainsString('<title>leaf()</title>', $html);
        $this->assertStringContainsString('Green debug dump', $html);
        $this->assertStringContainsString('green', $html);
    }
}

class LeafTestUser extends Model
{
    protected string $table = 'users';
}
