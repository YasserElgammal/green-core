# Debugging with leaf()

`leaf($value)` is Green Framework's native dump-and-die helper. It renders a formatted dump of any PHP value, displays the call site and runtime context, then terminates execution immediately.

```php
leaf($user);
```

## Architecture

- `leaf()` is the global helper exposed by Composer through `src/helpers.php`.
- `DebugConfig` stores runtime limits and reads environment defaults.
- `Dumper` converts PHP values into a safe `DumpNode` tree.
- `DumpNode` is a presentation-neutral structure shared by all renderers.
- `HtmlRenderer` renders dark, collapsible web output.
- `CliRenderer` renders clean terminal output with ANSI colors.
- `DumpContext` captures file, line, memory usage, SAPI, timestamp, and request execution time.

The dumper and renderers are intentionally separate so framework internals can later reuse the normalized dump tree for logs, tests, or browser tooling without coupling to HTML.

## Supported Values

`leaf()` supports scalars, arrays, objects, Green models, exceptions, resources, and nested structures. It detects repeated object references and marks them as circular instead of recursing forever.

Green models are rendered as `model` nodes with class, table, primary key, primary key value, and resolved attributes. Exceptions are rendered as `exception` nodes with message, code, file, line, and a limited trace.

## Configuration

Publish the config file into an application:

```bash
php green publish:config leaf
```

This creates `config/leaf.php`:

```php
<?php

return [
    'max_depth' => 6,
    'max_items' => 100,
    'max_string_length' => 20000,
    'dark_theme' => true,
];
```

`leaf()` reads `config/leaf.php` automatically when the file exists. You can override the config file path with `GREEN_LEAF_CONFIG` or `LEAF_CONFIG`.

You can also configure it at runtime:

```php
leaf_config([
    'max_depth' => 6,
    'max_items' => 100,
]);
```

Or override individual values via environment:

```dotenv
GREEN_LEAF_DEPTH=6
GREEN_LEAF_ITEMS=100
GREEN_LEAF_STRING_LIMIT=20000
GREEN_LEAF_DARK=true
```

Defaults are conservative: depth `5`, items `50`, string length `10000`, dark theme enabled.

## Best Practices

Use `leaf()` only during development or local debugging. Since it intentionally terminates the script and returns a debug response, it should not remain in committed application code paths.

Keep depth and item limits low for large model graphs, API payloads, and ORM-style relation trees. Raise limits only when investigating a specific structure.

Prefer dumping focused values instead of entire containers or application instances. Smaller dumps are faster, easier to read, and less likely to expose secrets.

Avoid dumping credentials, tokens, session payloads, or customer data in shared environments.

## Performance Notes

The dumper never walks more than `max_depth` levels or `max_items` entries per collection/object. Strings are truncated at `max_string_length`. Repeated object references are tracked by `spl_object_id()` to prevent circular object graphs from exhausting memory.

Arrays that contain recursive references are still protected by the depth limit. PHP does not expose a stable, mutation-free array identity, so depth limits are the safe guard for recursive array structures.
