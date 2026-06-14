# Changelog

All notable changes to this project will be documented in this file.

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
