# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-01-15

### Added
- Fluent API for SQL query building (SELECT, INSERT, UPDATE, DELETE, COUNT)
- Automatic parameter binding for SQL injection protection
- Raw SQL expression support via `raw()` method
- WHERE conditions with operators (=, !=, <, >, <=, >=, LIKE)
- IN and BETWEEN operator support
- JOIN support (INNER, LEFT, RIGHT)
- GROUP BY and ORDER BY clauses
- LIMIT and OFFSET support
- FlightPHP SimplePdo integration
- Legacy PDO and MySQLi support
- Comprehensive PHPUnit test suite (36 tests, 71 assertions)
- PHPStan Level Max compliance
- English documentation and examples

### Features
- Zero dependencies (only 2 core files)
- PHP 7.4+ support
- PSR-4 autoloading
- Framework agnostic design

### Added
- Initial release
- Fluent API for SQL query building
- Support for SELECT, INSERT, UPDATE, DELETE, COUNT queries
- JOIN support (INNER, LEFT, RIGHT)
- WHERE conditions with operators (=, !=, <, >, <=, >=, LIKE)
- GROUP BY, ORDER BY, LIMIT, OFFSET support
- Table aliases
- Automatic parameter binding for SQL injection protection
- PSR-4 autoloading
- Composer package configuration

[Unreleased]: https://github.com/knifelemon/generate-query/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/knifelemon/generate-query/releases/tag/v1.0.0
