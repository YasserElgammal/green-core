<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery;

use YasserElgammal\Green\Database\IncludeQuery\Parser\IncludeParser;
use YasserElgammal\Green\Database\IncludeQuery\Resolver\IncludeResolver;
use YasserElgammal\Green\Database\IncludeQuery\Resolver\ResolvedInclude;
use YasserElgammal\Green\Database\IncludeQuery\Validation\IncludeValidator;

/**
 * Orchestrates the full Include Query Language pipeline.
 *
 *   Raw string → Parse → Validate → Resolve → ResolvedInclude[]
 *
 * Single entry point used by Table to process advanced include syntax.
 *
 * Usage:
 *   $engine   = new IncludeQueryEngine();
 *   $resolved = $engine->process(
 *       'comments(limit:5,order:desc).author(select:id|name)',
 *       $relations,
 *       static::class,
 *   );
 */
final class IncludeQueryEngine
{
    private readonly IncludeParser    $parser;
    private readonly IncludeValidator $validator;
    private readonly IncludeResolver  $resolver;

    public function __construct()
    {
        $this->parser    = new IncludeParser();
        $this->validator = new IncludeValidator();
        $this->resolver  = new IncludeResolver();
    }

    /**
     * Process a raw include query string through the full pipeline.
     *
     * @param  string                               $input       Raw include query
     * @param  array<string, array<string, mixed>>  $relations   Table's relation registry
     * @param  string                               $tableClass  FQCN for error messages
     * @return ResolvedInclude[]
     *
     * @throws \YasserElgammal\Green\Database\IncludeQuery\Exceptions\SyntaxException
     * @throws \YasserElgammal\Green\Database\IncludeQuery\Exceptions\UnknownRelationException
     * @throws \YasserElgammal\Green\Database\IncludeQuery\Exceptions\UnknownOperationException
     * @throws \YasserElgammal\Green\Database\IncludeQuery\Exceptions\InvalidOperationValueException
     */
    public function process(string $input, array $relations, string $tableClass): array
    {
        // 1. Parse → AST
        $nodes = $this->parser->parse($input);

        if (empty($nodes)) {
            return [];
        }

        // 2. Validate against schema
        $this->validator->validate($nodes, $relations, $tableClass);

        // 3. Resolve → constraint closures
        return $this->resolver->resolve($nodes, $relations);
    }

    /**
     * Check whether an include string contains advanced IQL syntax.
     *
     * Simple includes like 'posts,comments' return false.
     * Advanced includes like 'posts(limit:5)' return true.
     */
    public static function isAdvancedSyntax(string $input): bool
    {
        return str_contains($input, '(');
    }
}
