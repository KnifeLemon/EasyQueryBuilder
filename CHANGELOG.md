# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Query builder reuse methods: clearWhere(), clearSelect(), clearJoin(), clearGroupBy(), clearOrderBy(), clearLimit(), clearAll()
- Comprehensive "Understanding build() Return Value" section in README
- Enhanced documentation with query result examples for all operators
- Tracy Debugger integration with custom panel
- QueryLogger and QueryPanel for development debugging

### Changed
- Updated all examples to use correct namespace (EasyQuery/Builder)
- Improved OR Conditions examples with multiple conditions
- Renamed test files from GenerateQuery* to Builder* to match class names
- Updated all documentation references from GenerateQuery to EasyQuery

## [1.0.0] - 2026-01-15

### Added
- Initial release with fluent API for SQL query building
- Support for SELECT, INSERT, UPDATE, DELETE, COUNT queries
- Automatic parameter binding for SQL injection protection
- Raw SQL expression support via `raw()` method
- WHERE conditions with operators (=, !=, <, >, <=, >=, LIKE)
- IN and BETWEEN operator support
- JOIN support (INNER, LEFT, RIGHT)
- Table aliases
- GROUP BY and ORDER BY clauses
- LIMIT and OFFSET support
- FlightPHP SimplePdo integration examples
- Legacy PDO and MySQLi support examples
- Comprehensive PHPUnit test suite (44 tests, 97 assertions)
- PHPStan Level Max compliance
- English documentation and examples

### Features
- Zero dependencies (4 core files: Builder, BuilderRaw, QueryLogger, QueryPanel)
- PHP 7.4+ support
- PSR-4 autoloading
- Framework agnostic design
- Database agnostic (works with any DB driver)

[Unreleased]: https://github.com/knifelemon/EasyQueryBuilder/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/knifelemon/EasyQueryBuilder/releases/tag/v1.0.0
