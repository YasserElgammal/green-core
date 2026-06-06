<?php

namespace YasserElgammal\Green\Debug\Renderers;

use YasserElgammal\Green\Debug\DebugConfig;
use YasserElgammal\Green\Debug\DumpContext;
use YasserElgammal\Green\Debug\DumpNode;
use YasserElgammal\Green\Debug\RendererInterface;

final class HtmlRenderer implements RendererInterface
{
    public function render(DumpNode $node, DumpContext $context, DebugConfig $config): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>leaf()</title><style>' . $this->styles($config) . '</style></head>'
            . '<body><main class="leaf">'
            . '<header><div><span class="brand">leaf()</span><span class="muted">Green debug dump</span></div>'
            . '<div class="context">'
            . $this->badge('File', $context->file . ':' . $context->line)
            . $this->badge('Memory', $this->bytes($context->memoryUsage))
            . $this->badge('Time', number_format($context->executionTime * 1000, 2) . ' ms')
            . $this->badge('SAPI', $context->sapi)
            . '</div></header>'
            . '<section class="dump">' . $this->node($node) . '</section>'
            . '</main></body></html>';
    }

    private function node(DumpNode $node, ?string $key = null): string
    {
        $keyHtml = $key === null ? '' : '<span class="key">' . $this->e($key) . '</span><span class="arrow">=&gt;</span>';
        $meta = $this->meta($node);
        $flags = $this->flags($node);

        if ($node->circular) {
            return '<div class="row">' . $keyHtml . '<span class="type">' . $this->e($node->type)
                . '</span><span class="warning">circular reference</span>' . $meta . '</div>';
        }

        if ($node->children === []) {
            return '<div class="row">' . $keyHtml . '<span class="type">' . $this->e($node->type)
                . '</span><span class="value">' . $this->value($node) . '</span>' . $meta . $flags . '</div>';
        }

        $children = '';
        foreach ($node->children as $childKey => $child) {
            $children .= $this->node($child, (string) $childKey);
        }

        return '<details open><summary>' . $keyHtml . '<span class="type">' . $this->e($node->type)
            . '</span>' . $meta . $flags . '</summary><div class="children">' . $children . '</div></details>';
    }

    private function value(DumpNode $node): string
    {
        return match ($node->type) {
            'null' => '<span class="null">null</span>',
            'bool' => $node->value ? 'true' : 'false',
            'string' => '"' . $this->e((string) $node->value) . '"',
            default => $this->e((string) $node->value),
        };
    }

    private function meta(DumpNode $node): string
    {
        if ($node->meta === []) {
            return '';
        }

        $items = [];
        foreach ($node->meta as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $items[] = $this->e($key) . ': ' . $this->e(is_scalar($value) ? (string) $value : get_debug_type($value));
        }

        return $items === [] ? '' : '<span class="meta">' . implode(' · ', $items) . '</span>';
    }

    private function flags(DumpNode $node): string
    {
        $flags = [];

        if ($node->truncated) {
            $flags[] = 'truncated';
        }

        if ($node->meta['reason'] ?? null) {
            $flags[] = (string) $node->meta['reason'];
        }

        if ($flags === []) {
            return '';
        }

        return '<span class="warning">' . $this->e(implode(', ', $flags)) . '</span>';
    }

    private function badge(string $label, string $value): string
    {
        return '<span class="badge"><strong>' . $this->e($label) . '</strong>' . $this->e($value) . '</span>';
    }

    private function styles(DebugConfig $config): string
    {
        if (!$config->darkTheme) {
            return ':root{color-scheme:light}body{margin:0;background:#f6f8fb;color:#111827;font:14px/1.55 ui-monospace,SFMono-Regular,Consolas,monospace}.leaf{padding:24px}header{background:#fff;border:1px solid #d9e0ea;border-radius:8px;padding:18px;margin-bottom:16px}.brand{color:#15803d;font-size:22px;font-weight:800;margin-right:10px}.muted,.meta{color:#64748b}.context{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}.badge{background:#eef2f7;border:1px solid #d9e0ea;border-radius:6px;padding:7px 10px}.badge strong{margin-right:6px}.dump{background:#fff;border:1px solid #d9e0ea;border-radius:8px;padding:18px;overflow:auto}.row,summary{padding:4px 0}.children{margin-left:22px;border-left:1px solid #d9e0ea;padding-left:14px}.type{color:#047857;font-weight:700;margin-right:8px}.key{color:#0369a1;margin-right:8px}.arrow{color:#94a3b8;margin-right:8px}.value{color:#334155;white-space:pre-wrap}.warning{color:#b45309;margin-left:10px}.null{color:#64748b}summary{cursor:pointer}';
        }

        return ':root{color-scheme:dark}body{margin:0;background:#0b1015;color:#e5edf5;font:14px/1.55 ui-monospace,SFMono-Regular,Consolas,monospace}.leaf{padding:24px}header{background:#101820;border:1px solid #203040;border-radius:8px;padding:18px;margin-bottom:16px;box-shadow:0 18px 40px rgba(0,0,0,.28)}.brand{color:#56d364;font-size:22px;font-weight:800;margin-right:10px}.muted,.meta{color:#8b9bab}.context{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}.badge{background:#0d141b;border:1px solid #203040;border-radius:6px;padding:7px 10px}.badge strong{color:#c7d1db;margin-right:6px}.dump{background:#101820;border:1px solid #203040;border-radius:8px;padding:18px;overflow:auto}.row,summary{padding:4px 0}.children{margin-left:22px;border-left:1px solid #273848;padding-left:14px}.type{color:#7ee787;font-weight:700;margin-right:8px}.key{color:#79c0ff;margin-right:8px}.arrow{color:#697786;margin-right:8px}.value{color:#dbe7f3;white-space:pre-wrap}.warning{color:#f2cc60;margin-left:10px}.null{color:#8b9bab}summary{cursor:pointer}';
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

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
