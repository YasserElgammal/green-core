# Green Core Architecture Review

> **Reviewer**: Senior Software Architect  
> **Framework**: Green Core v1.8.0  
> **Date**: 2026-06-13  
> **Scope**: Full architectural review of all framework subsystems  

---

## Executive Summary

Green Core is a **promising, lightweight PHP framework** that demonstrates clear architectural intent and genuine understanding of framework design principles. It successfully implements core framework subsystems — routing (attribute-based), ORM (Table Gateway/Data Mapper hybrid), middleware pipeline, error handling, logging, file storage, HTTP client, translation, CSRF protection, notifications, and a debugger — all while remaining lean and comprehensible.

**Overall Quality**: 🟡 **Solid Foundation with Significant Growth Opportunities**

The framework's main strength lies in its **clean separation between business logic concerns** (Model is a pure DTO, Table handles persistence) and its **strategy-based extensibility** (RelationRegistry, AggregationRegistry, LoggerInterface drivers, DriveDriverInterface). Several components — Drive, Connect, Logging, Translation, Debug — demonstrate professional-grade architecture with proper contracts, managers, and testability hooks (fake drivers).

However, the framework faces **four structural risks** that will limit scalability:

1. **Absence of a Service Container** — The framework wires dependencies manually through constructor calls and `$GLOBALS`, creating hidden coupling and making dependency injection inconsistent.
2. **Fragmented Configuration** — Config is scattered across `$_ENV`, env files, config PHP files, and hardcoded defaults with no unified repository.
3. **Static/Singleton Overuse** — `Database`, `Schema`, `View`, `TranslatorManager`, and `RelationRegistry` rely on static state, making testing difficult and preventing multi-tenancy.
4. **Missing HTTP Kernel abstraction** — The `Application::handle()` method directly delegates to `Router::dispatch()` without an exception-handling boundary, termination hooks, or response-sending lifecycle.

These are not fatal flaws — they are natural growing pains of a framework evolving organically. The codebase is clean enough that all four can be addressed incrementally without a rewrite.

---

## Current Framework Flow

### HTTP Lifecycle

```
index.php (user app)
  │
  ├── Dotenv loads .env
  ├── define('BASE_PATH', __DIR__)
  │
  └── new Application()
        ├── bootErrorHandling()       → LogManager + GreenErrorKernel registered
        ├── bootDrive()               → DriveManager + Drive + global drive() helper
        ├── bootConnect()             → ConnectManager + Connect + global connect() helper
        ├── bootValidationTranslation() → Respect\Validation translator wired
        └── Router constructed

  $app->handle(Request::capture())
        │
        └── Router::dispatch($request)
              ├── FastRoute dispatcher resolves route
              ├── Route attributes extracted from controller methods
              ├── Route params set as request attributes
              ├── Global + route middleware merged
              └── runPipeline()
                    ├── Middleware chain executed (reversed, onion model)
                    ├── ControllerResolver auto-wires controller
                    ├── Method parameters resolved (Request, Payload, route vars)
                    ├── Controller method invoked
                    └── Response coerced (array→JsonResponse, string→Response)

  $response->send()
        ├── http_response_code()
        ├── Headers sent
        └── Content echoed
```

**Key observations:**
- No try/catch around `dispatch()` in `handle()` — unhandled exceptions bypass `ExceptionHandler`
- No response termination hooks (e.g., session save, log flush)
- `ExceptionHandler` exists but is not wired into the request lifecycle
- `Request::capture()` happens outside the framework — no framework control over request creation

### Console Lifecycle

```
green (CLI entry point)
  │
  └── new Console\Kernel()
        ├── $coreCommands registered (11 built-in commands)
        ├── $commands registered (user-defined, set by subclass)
        │
        └── Console\Application (extends Symfony Console)
              └── run() dispatches to matched Command
                    ├── BaseCommand::execute() sets up SymfonyStyle IO
                    └── Concrete command's handle() method runs
```

**Key observations:**
- Console kernel does not boot `Application` — no access to Drive, Connect, Logging, etc. unless commands manually bootstrap
- `BaseCommand::getMigrationRunner()` manually wires `Database::getConnection()` and `Schema::setPdo()` — fragile
- No shared "boot" phase between HTTP and CLI

### Database Lifecycle

```
First database access
  │
  └── Database::getConnection() (static singleton)
        ├── Reads $_ENV for DB credentials
        └── DriverManager::getConnection() → Doctrine DBAL Connection

Table Gateway usage:
  │
  └── new UserTable() extends Table
        ├── Constructor receives Model blueprint
        ├── Connection obtained from Database::getConnection()
        ├── CRUD: fetchAll(), fetchById(), insert(), save(), delete()
        ├── include(): queue eager-load relations
        ├── includeCount()/includeSum()/etc.: queue aggregations
        └── loadIncludes(): execute relation loaders (1 query per relation, no N+1)
              ├── RelationRegistry resolves loader strategy
              ├── Nested dot-notation parsed into include tree
              └── Child Table Gateways resolved by convention (Models\X → Tables\XTable)
```

**Key observations:**
- Static singleton means no multi-database support without manual `setConnection()` calls
- Schema uses raw PDO while Table uses Doctrine DBAL — two different abstraction layers for the same database
- Table Gateway requires instantiation with a blueprint Model — mildly awkward API

### Service Provider Lifecycle

**There is no service provider system.** All bootstrapping is hardcoded in `Application::__construct()`. Components are wired manually with `$GLOBALS`-based helper registration.

---

## Strengths

### 1. Clean Model/Table Separation
The decision to make `Model` a pure DTO and `Table` the persistence gateway is architecturally sound. It avoids Active Record's god-object problem and makes models trivially serializable.

### 2. Strategy Pattern in ORM Relations
`RelationRegistry` + `RelationLoader` interface allows adding new relation types (e.g., `morphMany`) by implementing one interface and calling `RelationRegistry::register()`. No changes to `Table` required. The same pattern is used for `AggregationRegistry`.

### 3. Include Query Language (IQL)
The advanced include syntax with constraint closures, nested dot-notation, and programmatic aggregations is a unique, powerful feature that surpasses many established ORMs.

### 4. Comprehensive Error/Logging Pipeline
The three-layer error system (GreenErrorKernel → ErrorRecord → LogManager → drivers) with deduplication, rate limiting, fingerprinting, and driver isolation is production-grade.

### 5. Drive & Connect Architecture
Both subsystems follow the same Manager → Facade → Driver pattern with:
- Contract-based drivers
- Config-driven multi-disk/multi-connection support
- `fake()` methods for testing
- Global helper access with proper initialization guards

### 6. Attribute-Based Routing
Using PHP 8 attributes (`#[Route(...)]`) for route definition is modern, discoverable, and avoids the maintenance burden of separate route files.

### 7. Payload Validation as Type-Safe Request Objects
The `Payload` pattern (extending `Request`, auto-validating in constructor) provides type-safe, validated request data directly injectable into controller methods. The `authorize()` hook adds authorization at the request level.

### 8. CSRF Protection Depth
The CSRF system is thorough: per-token IDs, session-based storage, configurable header/input names, route exclusions, middleware integration, Twig helpers, and post-render enforcement in `View::render()`.

### 9. Translation System Maturity
The translation subsystem demonstrates advanced architecture: provider chain (JSON files, database), locale resolution chain, file/in-memory caching, interpolation, pluralization with ICU-like rules, and a fluent builder API.

### 10. Debug Dumper (Leaf)
A custom debug dumper with configurable depth/items/string limits, CLI/HTML renderers, and caller context is a strong DX feature, especially with project-level config support.

---

## Main Architectural Risks

### Risk 1: No Service Container — Severity: 🔴 Critical

**Impact**: Without a container, every component wires its own dependencies. This leads to:
- `$GLOBALS` for sharing instances (`__green_log_manager`, `__green_drive_instance`, `__green_connect_instance`)
- Static singletons (`Database::$connection`, `View::$twig`, `TranslatorManager::$instance`, `Schema::$pdo`)
- `new` expressions scattered throughout boot code
- Impossible to swap implementations for testing without the specific `fake()` hooks each component manually provides

**Risk**: As the framework grows, adding new subsystems requires adding new `$GLOBALS` keys, new `_set_instance()` helpers, and new `boot*()` methods — a linear scaling problem.

### Risk 2: No Exception-Handling Boundary in HTTP Kernel — Severity: 🔴 Critical

**Impact**: `Application::handle()` does `return $this->router->dispatch($request)` with no try/catch. If a controller throws, the exception propagates to `GreenErrorKernel`'s global handler which logs it but cannot render a proper HTTP response. The `ExceptionHandler` class exists but is **never called** in the request lifecycle.

**Risk**: Users in production see raw PHP error output or blank pages instead of proper error responses.

### Risk 3: Configuration Fragmentation — Severity: 🟠 High

**Impact**: Configuration is sourced from:
- `$_ENV` directly (`DB_HOST`, `APP_DEBUG`, `LOG_DIR`, etc.)
- Config PHP files (`config/drive.php`, `config/connect.php`) resolved via `resolveConfigFile()`
- Hardcoded defaults spread across multiple classes
- `CsrfConfig` reads from `config/csrf.php`
- `DebugConfig::fromProjectConfig()` reads from `config/leaf.php`

**Risk**: No central place to see "what is configurable" or override config programmatically. Adding a new config key means knowing which class reads it.

### Risk 4: Static State Makes Testing Fragile — Severity: 🟠 High

**Impact**: `Database::getConnection()`, `Schema::setPdo()`, `View::init()`, `TranslatorManager::getInstance()`, and `RelationRegistry::$loaders` all use static state. Tests must manually reset each one, and parallel test execution is unsafe.

### Risk 5: Code Duplication in Resolvers — Severity: 🟡 Medium

**Impact**: `ControllerResolver` and `MiddlewareResolver` share ~80% identical code (the `build()`, `resolveParameter()`, circular dependency detection). This duplication means bugs must be fixed in two places.

### Risk 6: Schema Hardcoded to MySQL — Severity: 🟡 Medium

**Impact**: `Blueprint::buildCreateStatements()` generates MySQL-specific SQL (`ENGINE=InnoDB`, `CHARSET=utf8mb4`, backtick quoting). `Schema::hasTable()` queries `information_schema` with MySQL-specific syntax. The `SELECT DATABASE()` call fails on PostgreSQL/SQLite.

### Risk 7: Missing Authorization Layer — Severity: 🟡 Medium

**Impact**: `Payload::authorize()` exists but returns `true` by default and throws a generic `\Exception`. There's no Gate/Policy system, no role-based access control, and no way to check permissions outside of Payload.

### Risk 8: No Event System — Severity: 🟡 Medium

**Impact**: Cross-cutting concerns (audit logging, cache invalidation, notification dispatch on model changes) must be hardcoded into business logic. No way to hook into framework lifecycle events.

---

## Improvement Roadmap

| Priority | Area | Issue | Recommendation | Complexity | Suggested Files |
|----------|------|-------|----------------|-----------|-----------------|
| 🔴 Critical | HTTP Kernel | `handle()` has no try/catch; `ExceptionHandler` is unused | Wrap dispatch in try/catch, delegate to `ExceptionHandler` | Easy | `Application.php` |
| 🔴 Critical | Service Container | No IoC container; `$GLOBALS` + statics for DI | Introduce a lightweight `Container` class with bind/resolve/singleton | Medium | New `Container.php`, `Application.php` |
| 🔴 Critical | Config | Fragmented config sources | Create `Config` repository with dot-notation access | Medium | New `Config/Repository.php`, `Application.php` |
| 🟠 High | Service Providers | Hardcoded boot sequence | Introduce `ServiceProvider` base class with `register()` + `boot()` | Medium | New `ServiceProvider.php`, `Application.php` |
| 🟠 High | Testing | Static state prevents clean tests | Pass container to components, remove statics | Hard | `Database.php`, `Schema.php`, `View.php`, `TranslatorManager.php` |
| 🟠 High | Code Duplication | Resolver duplication | Extract shared `AbstractResolver` or use Container for resolution | Easy | `ControllerResolver.php`, `MiddlewareResolver.php` |
| 🟠 High | Console Boot | CLI doesn't boot Application | Share boot logic between HTTP and Console | Medium | `Console/Kernel.php`, `Application.php` |
| 🟡 Medium | Authorization | No Gate/Policy system | Add `Gate` class with `define()`/`allows()`/`denies()` | Medium | New `Security/Gate.php` |
| 🟡 Medium | Events | No event dispatcher | Add lightweight `EventDispatcher` with listener registration | Medium | New `Events/Dispatcher.php`, `Events/Event.php` |
| 🟡 Medium | Database | Static singleton, MySQL-only schema | Support multiple connections, abstract SQL generation | Hard | `Database.php`, `Schema/Blueprint.php` |
| 🟡 Medium | Request | Thin wrapper, missing features | Add `all()`, `only()`, `except()`, `bearerToken()`, `isJson()`, `wantsJson()` | Easy | `Http/Request.php` |
| 🟡 Medium | Response | No `getHeaders()` accessor | Add header retrieval and `toArray()` for testability | Easy | `Http/Response.php` |
| 🟡 Medium | Middleware | Interface exists but not enforced | Enforce `MiddlewareInterface` in `MiddlewareResolver` | Easy | `MiddlewareResolver.php` |
| 🟢 Low | Caching | No caching abstraction | Add `Cache` manager with file/array/null drivers | Medium | New `Cache/` module |
| 🟢 Low | Jobs/Queues | No async job support | Add `Job` base class and simple sync/database queue driver | Hard | New `Queue/` module |
| 🟢 Low | Model | No dirty tracking, timestamps, or soft deletes | Add `$dirty`, `$original`, auto-timestamps, `SoftDeletes` trait | Medium | `Database/Model.php`, `Database/Table.php` |
| 🟢 Low | Performance | View caching disabled | Enable Twig compilation cache in production | Easy | `View/View.php` |
| 🟢 Low | Documentation | README is minimal | Generate API docs, add getting-started guide | Medium | `docs/`, `README.md` |

---

## Detailed Recommendations

### 1. Wire ExceptionHandler into HTTP Lifecycle — Priority: 🔴 Critical

**Current Issue:**
`Application::handle()` directly returns `$this->router->dispatch($request)`. If any exception escapes — a database error, a ValidationException, a custom 404 — it propagates to PHP's global exception handler (GreenErrorKernel), which logs but cannot render an HTTP response.

The `ExceptionHandler` class exists with full JSON/HTML rendering, debug mode support, and error code mapping — but it is **never instantiated or called** by the framework.

**Why It Matters:**
In production, unhandled exceptions cause blank pages or raw PHP errors. Every framework user must independently wrap their index.php in try/catch to get error pages.

**Recommended Solution:**
```php
public function handle(Request $request): Response
{
    try {
        return $this->router->dispatch($request);
    } catch (\Throwable $e) {
        return $this->exceptionHandler->handle($e, $request);
    }
}
```

**Files to Update:** `Application.php`  
**Complexity:** Easy  
**Backward Compatible:** Yes

---

### 2. Introduce a Lightweight Service Container — Priority: 🔴 Critical

**Current Issue:**
Dependencies are shared via `$GLOBALS` (`__green_log_manager`, `__green_drive_instance`, `__green_connect_instance`), each with a dedicated `*_set_instance()` function. Static singletons (`Database::$connection`, `View::$twig`) serve the same purpose elsewhere. This creates:
- Hidden global state
- No ability to swap implementations without touching internals
- No interface-based binding

**Why It Matters:**
Every new subsystem added to the framework requires a new `$GLOBALS` key, a new `_set_instance()` function, and a new helper function. This scales linearly with complexity and creates invisible coupling.

**Recommended Solution:**
Create a minimal `Container` class with:
```php
class Container {
    public function bind(string $abstract, callable|string $concrete): void;
    public function singleton(string $abstract, callable|string $concrete): void;
    public function make(string $abstract): mixed;
    public function has(string $abstract): bool;
}
```

Then replace `$GLOBALS`-based registration with container bindings. Keep helper functions (they're good DX) but have them resolve from the container:
```php
function drive(): Drive {
    return app()->make(Drive::class);
}
```

**Files to Create:** `Container.php`  
**Files to Update:** `Application.php`, `helpers.php`  
**Complexity:** Medium  
**Backward Compatible:** Yes — helpers keep the same signatures

---

### 3. Create a Unified Config Repository — Priority: 🔴 Critical

**Current Issue:**
Configuration is sourced from at least 5 different mechanisms:
1. `$_ENV` direct access (`$_ENV['DB_NAME']`, `$_ENV['APP_DEBUG']`)
2. `getenv()` fallback in `Application::resolveEnv()`
3. PHP config files (`require 'config/drive.php'`) returning arrays
4. Class-internal defaults (`JwtConfig::DEFAULTS`, `CsrfConfig` defaults)
5. Hardcoded values (`View::init(['cache' => false])`)

**Why It Matters:**
- No central registry to discover available config keys
- No config caching for production
- Cannot override config at runtime for testing
- Each component independently implements its own config loading logic

**Recommended Solution:**
```php
class Repository {
    public function __construct(array $items = []);
    public function get(string $key, mixed $default = null): mixed;  // supports dot-notation
    public function set(string $key, mixed $value): void;
    public function has(string $key): bool;
    public function all(): array;
}
```

Load all `config/*.php` files at boot, merge with env overrides, and inject into components:
```php
$config = new Repository();
$config->loadDirectory(BASE_PATH . '/config');
```

**Files to Create:** `Config/Repository.php`  
**Files to Update:** `Application.php`, individual component constructors  
**Complexity:** Medium  
**Backward Compatible:** Yes — existing `$_ENV` access can remain as fallback

---

### 4. Add Service Provider System — Priority: 🟠 High

**Current Issue:**
All bootstrapping lives in `Application::__construct()` as hardcoded `boot*()` calls. Users cannot hook into the boot process, and adding a new framework subsystem requires modifying `Application.php`.

**Why It Matters:**
- Third-party packages cannot register services
- Users cannot control boot order or conditionally load subsystems
- The Application class grows linearly with every new feature

**Recommended Solution:**
```php
abstract class ServiceProvider {
    public function __construct(protected Application $app) {}
    public function register(): void {}   // Bind into container
    public function boot(): void {}       // Post-registration setup
}
```

Application would iterate providers:
```php
foreach ($this->providers as $provider) {
    $provider->register();
}
foreach ($this->providers as $provider) {
    $provider->boot();
}
```

**Files to Create:** `ServiceProvider.php`, `Providers/LogServiceProvider.php`, etc.  
**Files to Update:** `Application.php`  
**Complexity:** Medium  
**Backward Compatible:** Yes — existing boot methods can be wrapped in providers

---

### 5. Unify Console and HTTP Boot — Priority: 🟠 High

**Current Issue:**
`Console\Kernel::handle()` creates a `Console\Application` and registers commands — but it never boots the main `Application`. This means CLI commands lack access to:
- Logging (`green_log()` won't work)
- Drive (`drive()` throws RuntimeException)
- Connect (`connect()` throws RuntimeException)
- Configuration
- Any service that requires Application bootstrap

`BaseCommand::getMigrationRunner()` works around this by manually calling `Database::getConnection()` and `Schema::setPdo()`, but this is fragile.

**Why It Matters:**
CLI commands that need database access, file storage, or logging must independently bootstrap these services, leading to inconsistent behavior between HTTP and CLI.

**Recommended Solution:**
Have `Console\Kernel` boot the main `Application` first:
```php
public function handle(): void
{
    $this->app = new \YasserElgammal\Green\Application();
    // ... register commands ...
    $consoleApp->run();
}
```

With a service container, CLI commands could receive dependencies via constructor injection.

**Files to Update:** `Console/Kernel.php`, `Console/Commands/BaseCommand.php`  
**Complexity:** Medium  
**Backward Compatible:** Yes

---

### 6. Extract Shared Resolver Logic — Priority: 🟠 High

**Current Issue:**
`ControllerResolver` and `MiddlewareResolver` contain nearly identical code:
- Both implement `build()` with reflection-based auto-wiring
- Both implement `resolveParameter()` with the same logic
- Both track `$resolving` for circular dependency detection
- Both have `bind()` for manual registration

The only difference is that `MiddlewareResolver::guardHandleMethod()` checks for a `handle()` method.

**Why It Matters:**
Bug fixes must be applied twice. When the container is introduced, both resolvers will need the same refactor.

**Recommended Solution:**
Extract a shared `AbstractResolver` or better — delegate resolution to the Service Container:
```php
class ControllerResolver {
    public function __construct(private Container $container) {}
    public function resolve(string $class): object {
        return $this->container->make($class);
    }
}
```

**Files to Update:** `Routing/ControllerResolver.php`, `Routing/MiddlewareResolver.php`  
**Complexity:** Easy  
**Backward Compatible:** Yes

---

### 7. Enforce MiddlewareInterface — Priority: 🟡 Medium

**Current Issue:**
`MiddlewareInterface` exists in `Middleware/MiddlewareInterface.php` but is **not enforced anywhere**. `MiddlewareResolver::guardHandleMethod()` only checks `method_exists($middleware, 'handle')` instead of checking the interface. Route middleware arrays accept `string|object` — any class with a `handle()` method works.

**Why It Matters:**
Without interface enforcement, IDE autocomplete and static analysis cannot verify middleware correctness. Duck-typing creates uncertainty about method signatures.

**Recommended Solution:**
Change `guardHandleMethod()` to check `$middleware instanceof MiddlewareInterface`.

**Files to Update:** `Routing/MiddlewareResolver.php`  
**Complexity:** Easy  
**Breaking:** Potentially — existing middleware not implementing the interface would break. Provide migration period with deprecation warning.

---

### 8. Enrich Request Object — Priority: 🟡 Medium

**Current Issue:**
`Request` is a thin wrapper over superglobals with limited utility methods. Missing common operations:
- `all()` — merged query + post data
- `only(['field1', 'field2'])` — subset of input
- `except(['password'])` — all except specific fields
- `bearerToken()` — extract from Authorization header
- `isJson()` / `wantsJson()` — content negotiation
- `ip()` — client IP with proxy support
- `fullUrl()` — complete URL with query string
- JSON body parsing for `Content-Type: application/json` requests

**Why It Matters:**
Every application using the framework must implement these utilities manually. API controllers especially need `bearerToken()` and JSON body parsing.

**Recommended Solution:**
Add these methods to `Http\Request`. Most are one-liners.

**Files to Update:** `Http/Request.php`  
**Complexity:** Easy  
**Backward Compatible:** Yes

---

### 9. Enable View Caching — Priority: 🟢 Low (High Impact)

**Current Issue:**
`View::init()` hardcodes `'cache' => false` for Twig. Every template is re-parsed on every request.

**Why It Matters:**
Template compilation is one of the most expensive operations in a request. Enabling caching can reduce response time by 20-40% for view-heavy applications.

**Recommended Solution:**
```php
public static function init(string $viewsPath, ?string $cachePath = null): void
{
    self::$twig = new Environment($loader, [
        'cache' => $cachePath ?? false,
    ]);
}
```

**Files to Update:** `View/View.php`  
**Complexity:** Easy  
**Backward Compatible:** Yes

---

### 10. Abstract Schema SQL Generation — Priority: 🟡 Medium

**Current Issue:**
`Blueprint::buildCreateStatements()` generates MySQL-specific SQL:
- `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4` suffix
- Backtick quoting (MySQL-specific; PostgreSQL uses double quotes)
- `Schema::hasTable()` uses `information_schema.TABLES` with MySQL's `TABLE_SCHEMA`

While `Schema::dropAllTables()` does handle SQLite, the majority of schema operations are MySQL-only.

**Why It Matters:**
Users on PostgreSQL or SQLite (common for testing) cannot use the migration system.

**Recommended Solution:**
Introduce a `Grammar` strategy:
```php
interface Grammar {
    public function compileCreateTable(string $table, array $columns, ...): string;
    public function compileColumnType(Column $column): string;
}
class MySqlGrammar implements Grammar { ... }
class SqliteGrammar implements Grammar { ... }
```

**Files to Create:** `Database/Schema/Grammar.php`, `Database/Schema/MySqlGrammar.php`  
**Files to Update:** `Database/Schema/Blueprint.php`, `Database/Schema/Schema.php`  
**Complexity:** Hard  
**Breaking:** No — current MySQL behavior becomes the default grammar

---

### 11. Add Event Dispatcher — Priority: 🟡 Medium

**Current Issue:**
No event system exists. Cross-cutting concerns must be hardcoded into business logic.

**Why It Matters:**
Common needs like "send email after user registration" or "clear cache when post is updated" require coupling the action to the side effect. Events decouple these.

**Recommended Solution:**
```php
class EventDispatcher {
    public function listen(string $event, callable|string $listener): void;
    public function dispatch(object $event): void;
}
```

Wire lifecycle events: `RequestReceived`, `RequestHandled`, `ExceptionOccurred`, `CommandStarting`, `CommandFinished`.

**Files to Create:** `Events/EventDispatcher.php`, `Events/Event.php`  
**Complexity:** Medium  
**Backward Compatible:** Yes — purely additive

---

### 12. Add Authorization Gate — Priority: 🟡 Medium

**Current Issue:**
`Payload::authorize()` provides per-request authorization, but there's no reusable authorization system. No Gate/Policy pattern, no role checks, no ability checks outside of Payload.

**Why It Matters:**
Authorization logic gets scattered across controllers and middleware. No way to ask "can this user edit this post?" in a standardized way.

**Recommended Solution:**
```php
class Gate {
    public function define(string $ability, callable $callback): void;
    public function allows(string $ability, mixed ...$arguments): bool;
    public function denies(string $ability, mixed ...$arguments): bool;
    public function authorize(string $ability, mixed ...$arguments): void; // throws
}
```

**Files to Create:** `Security/Authorization/Gate.php`  
**Complexity:** Medium  
**Backward Compatible:** Yes

---

### 13. Add Model Dirty Tracking & Auto-Timestamps — Priority: 🟢 Low

**Current Issue:**
`Model` has no concept of "original" vs "changed" attributes. `Table::save()` sends all attributes on update, even unchanged ones. No auto-management of `created_at`/`updated_at`.

**Why It Matters:**
- Unnecessary UPDATE queries write all columns
- No way to check if a model was modified
- Timestamps must be managed manually

**Recommended Solution:**
Add `$original` tracking, `isDirty()`, `getDirty()`, and auto-timestamps in `Table::save()`.

**Files to Update:** `Database/Model.php`, `Database/Table.php`  
**Complexity:** Medium  
**Backward Compatible:** Yes

---

### 14. Add Caching Abstraction — Priority: 🟢 Low

**Current Issue:**
No caching layer exists. The Translation system implements its own `FileTranslationCache` and `InMemoryTranslationCache`, but there's no reusable cache for the rest of the framework.

**Why It Matters:**
Database queries, API responses, config, and route resolution all benefit from caching. Without a framework-level cache, every component builds its own.

**Recommended Solution:**
```php
interface CacheInterface {
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, int $ttl = 0): bool;
    public function has(string $key): bool;
    public function forget(string $key): bool;
    public function flush(): bool;
}
```

Drivers: `ArrayCache`, `FileCache`, `NullCache`.

**Files to Create:** `Cache/CacheInterface.php`, `Cache/CacheManager.php`, `Cache/Drivers/`  
**Complexity:** Medium  
**Backward Compatible:** Yes

---

## Suggested Refactor Phases

### Phase 1: Stability & Consistency (1-2 weeks)

**Goal:** Fix critical gaps that affect reliability without changing the API surface.

| Task | Risk | Breaking |
|------|------|----------|
| Wire `ExceptionHandler` into `Application::handle()` | Low | No |
| Add `all()`, `only()`, `except()`, `bearerToken()`, JSON body parsing to `Request` | Low | No |
| Add `getHeaders()` to `Response` | Low | No |
| Enforce `MiddlewareInterface` in resolver (with deprecation warning) | Low | Soft |
| Extract shared `AbstractResolver` from Controller/Middleware resolvers | Low | No |
| Fix `View::render()` CSRF regex to handle multiline forms | Low | No |
| Add missing `getContent()`/`getStatusCode()` to `JsonResponse` for test inspection | Low | No |

### Phase 2: Developer Experience (2-3 weeks)

**Goal:** Improve the daily workflow for framework users.

| Task | Risk | Breaking |
|------|------|----------|
| Create `Config\Repository` with dot-notation access | Medium | No |
| Create lightweight `Container` class | Medium | No |
| Migrate `$GLOBALS`-based helpers to Container resolution | Medium | No — helpers keep same signatures |
| Create `ServiceProvider` base class | Low | No |
| Wrap existing `boot*()` calls in ServiceProviders | Medium | No |
| Unify Console and HTTP boot (Console\Kernel boots Application) | Medium | No |
| Enable Twig compilation cache via config | Low | No |
| Add `green_log()` level shortcut methods: `green_info()`, `green_error()`, etc. | Low | No |

### Phase 3: Extensibility (2-4 weeks)

**Goal:** Enable third-party packages and complex applications.

| Task | Risk | Breaking |
|------|------|----------|
| Add `EventDispatcher` with lifecycle events | Low | No |
| Add `Gate` authorization system | Low | No |
| Add `CacheManager` with file/array drivers | Medium | No |
| Support multiple database connections in `Database` | Medium | Soft (static → instance) |
| Abstract Schema Grammar (MySQL/SQLite) | Medium | No |
| Add Model dirty tracking & auto-timestamps | Low | No |
| Add soft deletes trait | Low | No |

### Phase 4: Advanced Framework Features (3-6 weeks)

**Goal:** Bring the framework to feature parity with production-grade frameworks.

| Task | Risk | Breaking |
|------|------|----------|
| Add Job/Queue system (sync + database drivers) | Medium | No |
| Add named routes and URL generation (`route('user.show', $id)`) | Medium | No |
| Add route groups with prefix/middleware/namespace | Medium | No |
| Add response macros and `StreamedResponse` | Low | No |
| Add `FormRequest` with redirect-back-with-errors for web forms | Medium | No |
| Add rate limiting middleware | Low | No |
| Add `Mailer` service wrapping Symfony Mailer properly | Low | No |

### Phase 5: Performance & Tooling (2-3 weeks)

**Goal:** Optimize for production and improve the developer toolchain.

| Task | Risk | Breaking |
|------|------|----------|
| Add config caching (`config:cache` command) | Low | No |
| Optimize route caching integration (already partially done) | Low | No |
| Add debug bar / profiler for development | Medium | No |
| Add `phpstan.neon` baseline and CI static analysis | Low | No |
| Add PHPUnit test suite for core components | Low | No |
| Generate API documentation from PHPDoc | Low | No |
| Create `green-skeleton` project template | Low | No |

---

## Backward Compatibility Notes

### Safe Changes (No Breaking)
- Adding methods to existing classes (Request, Response, Model)
- Adding new classes (Container, Config, EventDispatcher, Gate, Cache)
- Wrapping existing boot logic in ServiceProviders
- Wiring ExceptionHandler into handle()
- Adding CLI boot of Application

### Soft Breaking (Deprecation Period Recommended)
- **Enforcing MiddlewareInterface**: Existing middleware using duck-typing will need to add `implements MiddlewareInterface`. Provide a deprecation warning first.
- **Moving Database from static to instance**: Applications calling `Database::getConnection()` directly would need to transition to container resolution. Keep static method as deprecated proxy.
- **Removing `$GLOBALS` helpers**: Keep the `*_set_instance()` functions but mark as `@deprecated`. Have them delegate to the container.

### Hard Breaking (Major Version Required)
- Changing `Table` constructor signature
- Changing `Model` attribute visibility from `private` to `protected`
- Removing `Schema` static methods in favor of instance methods
- Changing `View::init()` / `View::render()` from static to instance

**Recommendation:** Defer hard-breaking changes to a v2.0 release. All Phase 1-3 work can be backward compatible.

---

## Final Architect Notes

Green Core is at a pivotal point. The fundamentals are genuinely strong — the Table Gateway pattern, the relation loading system, the error pipeline, the Drive/Connect architecture, and the translation engine all show real framework design thinking. This is not someone copying Laravel — it's someone who understands *why* Laravel makes the choices it makes and is building a lighter alternative with its own identity.

**What Green Core should become next:**

1. **A container-first framework.** The single most impactful change is introducing a Service Container. It transforms every other improvement from "adding more global state" to "registering a service." The container doesn't need to be complex — 200 lines with `bind()`, `singleton()`, `make()`, and auto-wiring is sufficient.

2. **A framework with clear boundaries.** Right now, the framework knows too much about itself — `Application` directly instantiates `DriveManager`, `ConnectManager`, and `LogManager`. Service providers create a natural boundary: each subsystem becomes a self-contained provider that can be included or excluded.

3. **A framework that boots once, serves everywhere.** The HTTP and CLI paths should share the same boot sequence. A request should be just one lifecycle within a booted application, not the trigger for booting.

4. **A framework that fails gracefully.** The `ExceptionHandler` is well-designed — it just needs to be connected. Once wired in, Green Core will handle errors better than many established frameworks.

5. **Keep the lightweight philosophy.** The temptation will be to add every feature Laravel has. Resist this. Green Core's value proposition is being the framework you can read in an afternoon. Every feature should earn its place.

The recommended priority order is clear: **Exception handling → Container → Config → Service Providers**. These four changes will transform Green Core from a framework prototype into a production-ready framework. Everything else is incremental improvement on a solid base.

Green Core has earned its name — there's genuine growth potential here.
