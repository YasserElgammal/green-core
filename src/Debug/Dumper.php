<?php

namespace YasserElgammal\Green\Debug;

use ReflectionObject;
use Throwable;
use YasserElgammal\Green\Database\Model;

final class Dumper
{
    /** @var array<int,int> */
    private array $seenObjects = [];

    public function __construct(private readonly DebugConfig $config = new DebugConfig())
    {
    }

    public function dump(mixed $value): DumpNode
    {
        $this->seenObjects = [];

        return $this->normalize($value, 0);
    }

    private function normalize(mixed $value, int $depth): DumpNode
    {
        if ($depth >= $this->config->maxDepth && (is_array($value) || is_object($value))) {
            return new DumpNode(get_debug_type($value), meta: ['reason' => 'Maximum depth reached'], truncated: true);
        }

        return match (true) {
            $value === null => new DumpNode('null', null),
            is_bool($value) => new DumpNode('bool', $value),
            is_int($value) => new DumpNode('int', $value),
            is_float($value) => new DumpNode('float', $value),
            is_string($value) => $this->stringNode($value),
            is_array($value) => $this->arrayNode($value, $depth),
            is_object($value) => $this->objectNode($value, $depth),
            is_resource($value) => new DumpNode(
                'resource',
                get_resource_type($value),
                ['id' => (int) $value],
            ),
            default => new DumpNode(get_debug_type($value), (string) $value),
        };
    }

    private function stringNode(string $value): DumpNode
    {
        $length = strlen($value);
        $truncated = $length > $this->config->maxStringLength;

        return new DumpNode(
            'string',
            $truncated ? substr($value, 0, $this->config->maxStringLength) : $value,
            ['length' => $length],
            truncated: $truncated,
        );
    }

    /**
     * @param array<mixed> $value
     */
    private function arrayNode(array $value, int $depth): DumpNode
    {
        $children = [];
        $count = count($value);
        $index = 0;

        foreach ($value as $key => $item) {
            if ($index >= $this->config->maxItems) {
                break;
            }

            $children[$key] = $this->normalize($item, $depth + 1);
            $index++;
        }

        return new DumpNode(
            'array',
            null,
            ['count' => $count],
            $children,
            truncated: $count > $this->config->maxItems,
        );
    }

    private function objectNode(object $value, int $depth): DumpNode
    {
        $id = spl_object_id($value);

        if (isset($this->seenObjects[$id])) {
            return new DumpNode(
                get_debug_type($value),
                null,
                ['class' => $value::class, 'object_id' => $id],
                circular: true,
            );
        }

        $this->seenObjects[$id] = $depth;

        if ($value instanceof Throwable) {
            return $this->throwableNode($value, $depth, $id);
        }

        if ($value instanceof Model) {
            return $this->modelNode($value, $depth, $id);
        }

        return $this->genericObjectNode($value, $depth, $id);
    }

    private function throwableNode(Throwable $value, int $depth, int $id): DumpNode
    {
        $children = [
            'message' => $this->normalize($value->getMessage(), $depth + 1),
            'code' => $this->normalize($value->getCode(), $depth + 1),
            'file' => $this->normalize($value->getFile(), $depth + 1),
            'line' => $this->normalize($value->getLine(), $depth + 1),
            'trace' => $this->normalize(array_slice($value->getTrace(), 0, $this->config->maxItems), $depth + 1),
        ];

        return new DumpNode(
            'exception',
            null,
            ['class' => $value::class, 'object_id' => $id],
            $children,
            truncated: count($value->getTrace()) > $this->config->maxItems,
        );
    }

    private function modelNode(Model $value, int $depth, int $id): DumpNode
    {
        return new DumpNode(
            'model',
            null,
            [
                'class' => $value::class,
                'object_id' => $id,
                'table' => $value->getTable(),
                'primary_key' => $value->getPrimaryKey(),
                'primary_key_value' => $value->getPrimaryKeyValue(),
            ],
            ['attributes' => $this->normalize($value->toArray(), $depth + 1)],
        );
    }

    private function genericObjectNode(object $value, int $depth, int $id): DumpNode
    {
        $reflection = new ReflectionObject($value);
        $children = [];
        $properties = $reflection->getProperties();
        $index = 0;

        foreach ($properties as $property) {
            if ($index >= $this->config->maxItems) {
                break;
            }

            $name = match (true) {
                $property->isPrivate() => $property->getName() . ' (private)',
                $property->isProtected() => $property->getName() . ' (protected)',
                default => $property->getName(),
            };

            $children[$name] = $property->isInitialized($value)
                ? $this->normalize($property->getValue($value), $depth + 1)
                : new DumpNode('uninitialized');
            $index++;
        }

        return new DumpNode(
            'object',
            null,
            ['class' => $value::class, 'object_id' => $id, 'property_count' => count($properties)],
            $children,
            truncated: count($properties) > $this->config->maxItems,
        );
    }
}
