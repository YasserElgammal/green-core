<?php

namespace YasserElgammal\Green\Debug\Renderers;

use YasserElgammal\Green\Debug\DebugConfig;
use YasserElgammal\Green\Debug\DumpContext;
use YasserElgammal\Green\Debug\DumpNode;
use YasserElgammal\Green\Debug\RendererInterface;

final class CliRenderer implements RendererInterface
{
    public function render(DumpNode $node, DumpContext $context, DebugConfig $config): string
    {
        return PHP_EOL
            . "\033[32mleaf()\033[0m "
            . "\033[90m{$context->file}:{$context->line}\033[0m"
            . PHP_EOL
            . "\033[90mMemory: " . $this->bytes($context->memoryUsage)
            . ' | Time: ' . number_format($context->executionTime * 1000, 2) . ' ms'
            . ' | SAPI: ' . $context->sapi . "\033[0m"
            . PHP_EOL . PHP_EOL
            . $this->node($node)
            . PHP_EOL;
    }

    private function node(DumpNode $node, int $indent = 0, ?string $key = null): string
    {
        $prefix = str_repeat('  ', $indent);
        $label = $key === null ? '' : "\033[36m{$key}\033[0m => ";

        if ($node->circular) {
            return $prefix . $label . "\033[33m{$node->type}\033[0m *circular* " . $this->meta($node);
        }

        if ($node->children === []) {
            return $prefix . $label . "\033[33m{$node->type}\033[0m " . $this->scalar($node) . $this->suffix($node);
        }

        $lines = [
            $prefix . $label . "\033[33m{$node->type}\033[0m " . $this->meta($node) . $this->suffix($node),
        ];

        foreach ($node->children as $childKey => $child) {
            $lines[] = $this->node($child, $indent + 1, (string) $childKey);
        }

        return implode(PHP_EOL, $lines);
    }

    private function scalar(DumpNode $node): string
    {
        return match ($node->type) {
            'null' => 'null',
            'bool' => $node->value ? 'true' : 'false',
            'string' => '"' . addcslashes((string) $node->value, "\0..\37\"\\") . '"',
            default => (string) $node->value,
        };
    }

    private function meta(DumpNode $node): string
    {
        if ($node->meta === []) {
            return '';
        }

        $pairs = [];
        foreach ($node->meta as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $pairs[] = $key . '=' . (is_scalar($value) ? (string) $value : get_debug_type($value));
        }

        return $pairs === [] ? '' : '[' . implode(', ', $pairs) . ']';
    }

    private function suffix(DumpNode $node): string
    {
        $parts = [];

        if ($node->truncated) {
            $parts[] = 'truncated';
        }

        if ($node->meta['reason'] ?? null) {
            $parts[] = (string) $node->meta['reason'];
        }

        return $parts === [] ? '' : ' ' . "\033[90m(" . implode(', ', $parts) . ")\033[0m";
    }

    private function bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return number_format($value, $unit === 0 ? 0 : 2) . ' ' . $units[$unit];
    }
}
