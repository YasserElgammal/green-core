# Changelog

All notable changes to this project will be documented in this file.

## [2.6.0] - 2026-09-10

### Added
- Added named-route URL generation through the `route()` function in Twig templates.
- Added container-resolved invokable signal listeners.
- Added HTTP lifecycle signals for received requests, handled responses, and occurred exceptions.
- Added console lifecycle signals with command names, exit codes, execution durations, and thrown exceptions.
- Added lifecycle signal constants and focused HTTP, console, and container-resolution regression tests.

## [2.5.1] - 2026-09-09

### Added
- Added the `view:clear` console command to remove compiled Twig templates while preserving the configured cache directory.
- Added `VIEW_CACHE` and `VIEW_CACHE_PATH` environment mappings for configuring Twig template caching.
- Added regression coverage for view-cache clearing, unsafe cache paths, and boolean environment-value conversion.

## [2.5.0] - 2026-09-02

### Added
- Added `SessionServiceProvider` to register `SessionManager` as an application-owned singleton.
- Added explicit `start()`, `isStarted()`, and `invalidate()` session lifecycle operations.
- Added session lifecycle and session-fixation regression tests.

### Changed
- Sessions now start lazily on the first operation instead of during `SessionManager` construction.
- Updated the `session()` helper to resolve the same container-owned `SessionManager` instance used by dependency injection.

### Security
- Added session invalidation that clears session data and rotates the session identifier.
- Verified that session identifier regeneration prevents session fixation while preserving authenticated session data.

### Upgrade Notes
- Initialize `Application` before calling `session()`; the helper now resolves its instance from the application container.

## [2.4.0] - 2026-07-28

### Added
- Added centralized configuration loading with ordered sources, recursive merging, immutable runtime snapshots, and cache fingerprints.
- Added typed configuration objects for application, cache, database, logging, mail, translation, and view services.
- Added configuration definitions, environment mappings, secret redaction, and extension through custom definitions.
- Added `config:cache`, `config:clear`, and `config:show` console commands.
- Added focused configuration architecture, lifecycle, cache, command, and lazy-service tests.
- Added `firebase/php-jwt` as an explicit runtime dependency.

### Changed
- Updated `Application` to bootstrap configuration before registering runtime providers.
- Updated runtime providers and services to consume container-owned typed configuration instead of reading project configuration directly.
- Made view and translation services resolve lazily through the application container.
- Expanded the internal architecture documentation for configuration precedence, immutability, caching, and dependency boundaries.

### Security
- Added redaction for sensitive configuration values shown through diagnostics and console output.

## [2.3.1] - 2026-07-18

### Added
- Added HTTP exception contracts and trace identifiers for error responses.
- Added application-boundary and exception-handler regression coverage.

### Changed
- Strengthened the application exception boundary with an independent emergency response when the configured exception handler fails.
- Improved exception rendering while preserving safe production responses.

## [2.3.0] - 2026-07-18

### Added
- Dedicated route registration, registry, matching, and matched-route dispatch components.
- Database relationship objects for `HasMany`, `HasOne`, `BelongsTo`, and `ManyToMany` relations.
- Table-driven eager loading and aggregate support backed by a relational registry.
- Pagination support for query results, including an expanded `Paginator` implementation.
- Fluent column selection support for `GreenQuery`.
- Container unit coverage for auto-wiring, service lifetimes, rebinding, defaults, circular dependencies, and failed-resolution recovery.

### Changed
- Refactored `Router` to delegate route registration, matching, and dispatch responsibilities to focused components.
- Expanded `Table` with automatic timestamp management and relation-aware querying.
- Improved include-query relation resolution and query-state handling.
- Made the service container the single source of truth for logging, drive, HTTP client, signals, authorization, and cache helpers.
- Kept legacy `*_set_instance()` helpers as deprecated container bindings for backward compatibility.

### Fixed
- Prevented resolved singletons from surviving a service rebind and ensured failed dependency resolutions always clean the circular-resolution stack.
- Resolved nested IQL relations declared through modern `relations()` methods, including relation DTO definitions.
- Added error-kernel unregistration so test and worker lifecycles restore their previous PHP error and exception handlers.

## [2.2.0] - 2026-07-11

### Added
- `GreenQuery` fluent query builder with condition, ordering, result-fetching, and aggregate operations.
- Query state management for composing and reusing database queries.
- Rate limiting with array and file stores, configuration publishing, and `ThrottleRequests` middleware.
- Routing middleware pipeline, route invoker, response normalizer, and policy middleware.
- Routing service provider for registering routing infrastructure with the application container.
- Relation loader registration and additional helpers for table-based models.

### Changed
- Refactored `Router` and `MiddlewareResolver` around the new routing pipeline.
- Expanded `Table` to integrate fluent queries and relation loading.
- Improved file cache path handling, directory creation, deletion, and cleanup behavior.
## [2.1.0] - 2026-06-29

### Added
- Cache manager with array, file, database, and Redis drivers.
- Policy-based authorization with policies, authorizers, policy attributes, and forbidden exceptions.
- Signal dispatcher and `SignalAware` support for application events.
- Named routes, URL generation, and routing policy checks.
- Database connection pooling and database-specific schema grammars for MySQL and SQLite.
- Model dirty tracking and automatic timestamp support.
- Console generators for authorizers, policies, events, and listeners.
- Service providers for authentication, caching, database connections, and signals.

### Changed
- Wired authentication, signals, caching, and routing helpers into the application bootstrap flow.
- Refactored schema compilation behind a database grammar abstraction.
- Expanded global helpers for caching, authorization, signals, and URL generation.

### Fixed
- Cleared PHP's file status cache after cache-file deletion to prevent stale existence checks.

## [2.0.0] - 2026-06-15

### Added
- Lightweight Dependency Injection Container (`Container`) with auto-wiring and singleton registration support.
- Service Provider system (`ServiceProvider` base class) to manage framework bootstrap logic cleanly.
- `ConfigRepository` to load and access nested configuration values using dot notation.
- Global helper functions `app()` and `config()`.
- Common utility methods to `Request` (such as `ip()`, `userAgent()`, `isSecure()`, `isAjax()`).
- Test helpers and `headers_sent()` guards to `Response`.
- Twig view template caching mechanism.

### Changed
- Refactored `Application` to extend `Container` and bootstrap services dynamically through Service Providers.
- Unified console (`Console\Kernel`) and HTTP kernel boot flows to use the centralized container boot sequence.
- Extracted routing middleware resolution logic into `AbstractResolver`.
- Wired `ExceptionHandler` as a core service bound to the container for customized global exception handling.
- Upgraded JSON encoding configuration flags in `JsonResponse` for better formatting and safety.

## [1.8.0] - 2026-06-06

### Added
- Debug dumper (Leaf), config stubs, and route resolvers.
- Table Gateway and Schema Blueprint classes for database query building and schema definition.

## [1.7.0] - 2026-05-31

### Added
- Database include query aggregation system with support for count, exists, sum, avg, min, and max operations.

## [1.6.0] - 2026-05-23

### Added
- HTTP connection client and driver architecture.
- JWT implementation and support.

## [1.5.2] - 2026-05-19

### Changed
- Refactored foundational `Application` and `Payload` classes.
- Updated base `Payload` validation class.

## [1.5.1] - 2026-05-17

### Added
- `TranslationClearCommand` for clearing translation caches.

## [1.5.0] - 2026-05-16

### Added
- Include Query Language (IQL) engine and Application bootstrap logic.
- Core `Application` class and global helper functions for logging, storage, and request handling.
- Drive storage abstraction with local and fake drivers.
- Comprehensive error handling and logging system including record normalization and driver support.
- Include Query Engine and relational loaders for flexible data eager loading.

## [1.4.0] - 2026-05-09

### Added
- CSRF protection with configuration, token management, and validation middleware.

## [1.3.0] - 2026-05-02

### Added
- Comprehensive translation system with support for providers, caching, pluralization, and locale resolution.

## [1.2.0] - 2026-04-11

### Added
- Migration commands and schema management (`feat(migrations)`).

## [1.1.1] - 2026-04-08

### Added
- Pagination support to `QueryBuilder` results.

## [1.1.0] - 2026-04-07

### Added
- Console commands and stubs for controller and model generation.

## [1.0.0] - 2026-04-05

### Added
- Initial release of the Green-Core framework.
