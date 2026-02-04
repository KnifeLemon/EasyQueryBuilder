# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.2] - 2026-02-04

### Added
- **ON DUPLICATE KEY UPDATE support**: Use `onDuplicateKeyUpdate()` method to handle duplicate key errors gracefully in INSERT queries (MySQL/MariaDB)
  - Basic value updates: `->onDuplicateKeyUpdate(['column' => 'value'])`
  - Increment values: `->onDuplicateKeyUpdate(['points' => Builder::raw('points + 100')])`
  - Use VALUES() function: `->onDuplicateKeyUpdate(['points' => Builder::raw('points + VALUES(points)')])`
- Test coverage for ON DUPLICATE KEY UPDATE functionality

---
**Note:** The following features were released in v1.0.1 but were initially marked as "Unreleased" and not included in the release notes above. 

```
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
```

## [1.0.1] - 2026-01-19

### Added
- **NOT IN operator support**: Use `['column' => ['NOT IN', [values]]]` in where conditions
- **Raw SQL with bindings**: `Builder::raw('COALESCE(amount, ?)', [0])` for parameterized raw expressions
- **Safe identifier validation**: `Builder::safeIdentifier($userInput)` validates column/table names
- **Safe raw expressions**: `Builder::rawSafe()` for user-provided column names with SQL injection protection
- **BuilderRaw::withIdentifiers()**: Create raw expressions with multiple safe identifier substitutions
- New tests for NOT IN, raw bindings, and identifier validation (62 tests, 129 assertions)

### Security
- Added protection for user-provided column names in raw SQL expressions
- Identifier validation only allows alphanumeric characters, underscores, and dots

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

[Unreleased]: https://github.com/knifelemon/EasyQueryBuilder/compare/v1.0.2...HEAD
[1.0.2]: https://github.com/knifelemon/EasyQueryBuilder/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/knifelemon/EasyQueryBuilder/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/knifelemon/EasyQueryBuilder/releases/tag/v1.0.0
