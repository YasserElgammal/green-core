<?php

namespace YasserElgammal\Green\Debug;

interface RendererInterface
{
    public function render(DumpNode $node, DumpContext $context, DebugConfig $config): string;
}
