<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Validation;

use YasserElgammal\Green\Database\IncludeQuery\Ast\IncludeNode;
use YasserElgammal\Green\Database\IncludeQuery\Exceptions\UnknownRelationException;
use YasserElgammal\Green\Database\IncludeQuery\Operations\OperationRegistry;

/**
 * Validates parsed IncludeNode ASTs against a Table's relation registry.
 *
 * Three-level validation:
 *   1. Relation existence — does the name exist in the registry?
 *   2. Operation existence — is the operation registered in OperationRegistry?
 *   3. Operation value validity — does the value pass the operation's validate()?
 *
 * Recursive — validates nested child nodes by resolving child Table classes.
 */
final class IncludeValidator
{
    /**
     * Validate an array of IncludeNodes against the given relation definitions.
     *
     * @param  IncludeNode[]                        $nodes
     * @param  array<string, array<string, mixed>>  $relations   The Table's $relations registry
     * @param  string                               $tableClass  FQCN for error messages
     *
     * @throws UnknownRelationException
     * @throws \YasserElgammal\Green\Database\IncludeQuery\Exceptions\UnknownOperationException
     * @throws \YasserElgammal\Green\Database\IncludeQuery\Exceptions\InvalidOperationValueException
     */
    public function validate(array $nodes, array $relations, string $tableClass): void
    {
        foreach ($nodes as $node) {
            $this->validateNode($node, $relations, $tableClass);
        }
    }

    /**
     * Validate a single IncludeNode (and its children recursively).
     */
    private function validateNode(IncludeNode $node, array $relations, string $tableClass): void
    {
        // 1. Relation existence
        if (!isset($relations[$node->relation])) {
            throw new UnknownRelationException(
                $node->relation,
                $tableClass,
                array_keys($relations),
            );
        }

        // 2 & 3. Validate each operation
        foreach ($node->operations->all() as $operation) {
            $handler = OperationRegistry::resolve($operation->name, $node->relation);
            $handler->validate($operation->rawValue);
        }

        // 4. Recursive child validation
        if ($node->child !== null) {
            $childRelations = $this->resolveChildRelations($relations[$node->relation]);

            $this->validateNode(
                $node->child,
                $childRelations,
                $this->resolveChildTableClass($relations[$node->relation]['model']),
            );
        }
    }

    /**
     * Resolve the child Table's relations registry for recursive validation.
     *
     * Convention: App\Models\Post → App\Tables\PostTable
     *
     * @return array<string, array<string, mixed>>
     */
    private function resolveChildRelations(array $config): array
    {
        $modelClass = $config['model'] ?? '';
        $tableClass = $this->resolveChildTableClass($modelClass);

        if (!class_exists($tableClass)) {
            // Can't validate nested relations if child Table doesn't exist
            // Return empty — will fail at runtime if a nested relation is used
            return [];
        }

        // Use reflection to read the protected $relations property
        $reflection = new \ReflectionClass($tableClass);

        if (!$reflection->hasProperty('relations')) {
            return [];
        }

        $property = $reflection->getProperty('relations');

        // Create a temporary instance to read the property value
        $instance = $reflection->newInstanceWithoutConstructor();
        $property->setAccessible(true);

        return $property->getValue($instance);
    }

    /**
     * Resolve the Table FQCN from a Model FQCN.
     *
     * Convention: App\Models\Post → App\Tables\PostTable
     */
    private function resolveChildTableClass(string $modelClass): string
    {
        $baseName   = (new \ReflectionClass($modelClass))->getShortName();
        $tableClass = str_replace('\\Models\\', '\\Tables\\', $modelClass);
        $tableClass = preg_replace(
            '/\\\\' . preg_quote($baseName, '/') . '$/',
            '\\' . $baseName . 'Table',
            $tableClass,
        );

        return $tableClass;
    }
}
