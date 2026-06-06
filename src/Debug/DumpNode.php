<?php

namespace YasserElgammal\Green\Debug;

final class DumpNode
{
    /**
     * @param array<string,mixed> $meta
     * @param array<string|int,self> $children
     */
    public function __construct(
        public readonly string $type,
        public readonly mixed $value = null,
        public readonly array $meta = [],
        public readonly array $children = [],
        public readonly bool $truncated = false,
        public readonly bool $circular = false,
    ) {
    }

    public function withMeta(string $key, mixed $value): self
    {
        $meta = $this->meta;
        $meta[$key] = $value;

        return new self(
            $this->type,
            $this->value,
            $meta,
            $this->children,
            $this->truncated,
            $this->circular,
        );
    }
}
