# EasyQuery

[![Latest Stable Version](https://poser.pugx.org/knifelemon/easy-query/v/stable)](https://packagist.org/packages/knifelemon/easy-query)
[![Total Downloads](https://poser.pugx.org/knifelemon/easy-query/downloads)](https://packagist.org/packages/knifelemon/easy-query)
[![Latest Unstable Version](https://poser.pugx.org/knifelemon/easy-query/v/unstable)](https://packagist.org/packages/knifelemon/easy-query)
[![License](https://poser.pugx.org/knifelemon/easy-query/license)](https://packagist.org/packages/knifelemon/easy-query)
[![PHP Version Require](https://poser.pugx.org/knifelemon/easy-query/require/php)](https://packagist.org/packages/knifelemon/easy-query)

A lightweight, fluent PHP SQL query builder that generates SQL and parameters. Designed to work with any database connection (PDO, MySQLi, FlightPHP SimplePdo).

## Features

- 🚀 **Fluent API** - Chain methods for readable query construction
- 💉 **SQL Injection Protection** - Automatic parameter binding with prepared statements
- 🔧 **Raw SQL Support** - Insert raw SQL expressions with `raw()`
- 🔄 **Multiple Query Types** - SELECT, INSERT, UPDATE, DELETE, COUNT
- 🔗 **JOIN Support** - INNER, LEFT, RIGHT joins with aliases
- 📊 **Advanced Conditions** - LIKE, IN, BETWEEN, comparison operators
- 🎯 **Database Agnostic** - Returns SQL + params, use with any DB connection
- 🪶 **Lightweight** - Minimal footprint with zero required dependencies

## Installation

### Via Composer

```bash
composer require knifelemon/easy-query
```

### Manual Installation

Download and include the files:

```php
require_once 'src/Builder.php';
require_once 'src/BuilderRaw.php';
```

## Quick Start

```php
use KnifeLemon\EasyQuery\Builder;

// Simple SELECT query
$q = Builder::table('users')
    ->select(['id', 'name', 'email'])
    ->where(['status' => 'active'])
    ->orderBy('id DESC')
    ->limit(10)
    ->build();

// Execute with PDO
$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);
$users = $stmt->fetchAll();
```

## Usage Examples

### SELECT Queries

#### Basic SELECT

```php
$q = Builder::table('users')
    ->select(['id', 'name', 'email'])
    ->where(['status' => 'active'])
    ->build();

// Result: 
// sql: "SELECT id, name, email FROM users WHERE status = ?"
// params: ['active']
```

#### SELECT with Alias

```php
$q = Builder::table('users')
    ->alias('u')
    ->select(['u.id', 'u.name'])
    ->where(['u.status' => 'active'])
    ->orderBy('u.created_at DESC')
    ->limit(10)
    ->build();
```

#### SELECT with JOIN

```php
$q = Builder::table('users')
    ->alias('u')
    ->select(['u.id', 'u.name', 'p.title', 'p.content'])
    ->innerJoin('posts', 'u.id = p.user_id', 'p')
    ->where(['u.status' => 'active'])
    ->orderBy('p.published_at DESC')
    ->build();
```

### WHERE Conditions

#### Simple Equality

```php
$q = Builder::table('users')
    ->where(['id' => 123, 'status' => 'active'])
    ->build();
// WHERE id = ? AND status = ?
```

#### Comparison Operators

```php
$q = Builder::table('users')
    ->where([
        'age' => ['>=', 18],
        'score' => ['<', 100],
        'name' => ['LIKE', '%john%']
    ])
    ->build();
```

#### IN Operator

```php
$q = Builder::table('users')
    ->where([
        'id' => ['IN', [1, 2, 3, 4, 5]]
    ])
    ->build();
// WHERE id IN (?, ?, ?, ?, ?)
```

#### BETWEEN Operator

```php
$q = Builder::table('products')
    ->where([
        'price' => ['BETWEEN', [100, 500]]
    ])
    ->build();
// WHERE price BETWEEN ? AND ?
```

#### OR Conditions

```php
$q = Builder::table('users')
    ->where(['role' => 'admin'])
    ->orWhere(['role' => 'moderator'])
    ->build();
// WHERE role = ? OR (role = ?)
```

### INSERT Queries

```php
$q = Builder::table('users')
    ->insert([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'status' => 'active'
    ])
    ->build();

// Result:
// sql: "INSERT INTO users SET name = ?, email = ?, status = ?"
// params: ['John Doe', 'john@example.com', 'active']
```

### UPDATE Queries

```php
$q = Builder::table('users')
    ->update(['status' => 'inactive', 'updated_at' => date('Y-m-d H:i:s')])
    ->where(['id' => 123])
    ->build();

// Result:
// sql: "UPDATE users SET status = ?, updated_at = ? WHERE id = ?"
// params: ['inactive', '2026-01-15 10:30:00', 123]
```

### DELETE Queries

```php
$q = Builder::table('users')
    ->delete()
    ->where(['id' => 123])
    ->build();

// Result:
// sql: "DELETE FROM users WHERE id = ?"
// params: [123]
```

### COUNT Queries

```php
$q = Builder::table('users')
    ->count()
    ->where(['status' => 'active'])
    ->build();

// Result:
// sql: "SELECT COUNT(*) AS cnt FROM users WHERE status = ?"
// params: ['active']
```

### Raw SQL Expressions

Use `raw()` when you need to insert SQL expressions directly without parameter binding:

```php
use KnifeLemon\EasyQuery\Builder;

// Update with SQL functions
$q = Builder::table('users')
    ->update([
        'points' => Builder::raw('GREATEST(0, points - 100)'),
        'updated_at' => Builder::raw('NOW()')
    ])
    ->where(['id' => 123])
    ->build();

// Result:
// sql: "UPDATE users SET points = GREATEST(0, points - 100), updated_at = NOW() WHERE id = ?"
// params: [123]
```

```php
// WHERE with raw SQL
$q = Builder::table('products')
    ->where([
        'price' => ['>', Builder::raw('(SELECT AVG(price) FROM products)')]
    ])
    ->build();
```

## Framework Integration

### FlightPHP Integration

GenerateQuery works with [FlightPHP](https://flightphp.com/)'s SimplePdo by generating SQL and parameters that you pass directly to SimplePdo methods.

```php
use KnifeLemon\EasyQuery\Builder;

// Register SimplePdo with FlightPHP
Flight::register('db', \flight\database\SimplePdo::class, [
    'mysql:host=localhost;dbname=myapp;charset=utf8mb4',
    'username',
    'password',
    [
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'utf8mb4\'',
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
]);

// In your FlightPHP route
Flight::route('GET /users', function() {
    $q = Builder::table('users')
        ->select(['id', 'name', 'email'])
        ->where(['status' => 'active'])
        ->orderBy('created_at DESC')
        ->limit(20)
        ->build();
    
    // SimplePdo returns Collection objects
    $users = Flight::db()->fetchAll($q['sql'], $q['params']);
    
    // Collection objects have getData() method that returns array
    $usersArray = array_map(fn($user) => $user->getData(), $users);
    
    Flight::json(['users' => $usersArray]);
});

// Using fetchField for COUNT queries (returns single value)
Flight::route('GET /users/count', function() {
    $q = Builder::table('users')
        ->count()
        ->where(['status' => 'active'])
        ->build();
    
    $count = Flight::db()->fetchField($q['sql'], $q['params']);
    
    Flight::json(['count' => (int)$count]);
});

// INSERT with FlightPHP
Flight::route('POST /users', function() {
    $data = Flight::request()->data;
    
    $q = Builder::table('users')
        ->insert([
            'name' => $data->name,
            'email' => $data->email,
            'created_at' => Builder::raw('NOW()')
        ])
        ->build();
    
    Flight::db()->runQuery($q['sql'], $q['params']);
    $userId = Flight::db()->lastInsertId();
    
    Flight::json(['success' => true, 'id' => $userId]);
});
```

### Legacy PHP / PDO Integration

```php
use KnifeLemon\EasyQuery\Builder;

// PDO connection
$pdo = new PDO('mysql:host=localhost;dbname=mydb', 'user', 'pass');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// SELECT with PDO
$q = Builder::table('users')
    ->select(['id', 'name', 'email'])
    ->where(['status' => 'active'])
    ->build();

$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// INSERT with PDO
$q = Builder::table('users')
    ->insert([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com'
    ])
    ->build();

$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);
$userId = $pdo->lastInsertId();

// UPDATE with PDO
$q = Builder::table('users')
    ->update(['status' => 'inactive'])
    ->where(['id' => $userId])
    ->build();

$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);
$affectedRows = $stmt->rowCount();
```

### MySQLi Integration

```php
use KnifeLemon\EasyQuery\Builder;

$mysqli = new mysqli('localhost', 'user', 'pass', 'mydb');

$q = Builder::table('users')
    ->select(['id', 'name', 'email'])
    ->where(['status' => 'active'])
    ->build();

// Prepare statement
$stmt = $mysqli->prepare($q['sql']);

// Bind parameters dynamically
$types = str_repeat('s', count($q['params'])); // 's' for string, adjust as needed
$stmt->bind_param($types, ...$q['params']);

$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
```

## API Reference

### Static Methods

#### `Builder::table(string $table): Builder`
Set the table name for the query.

#### `Builder::raw(string $value): BuilderRaw`
Create a raw SQL expression that will be inserted directly without parameter binding.

### Instance Methods

#### `alias(string $alias): self`
Set an alias for the table.

#### `select(string|array $columns = '*'): self`
Set the columns to select.

#### `where(array $conditions): self`
Add WHERE conditions. Multiple calls are combined with AND.

#### `orWhere(array $conditions): self`
Add OR WHERE conditions.

#### `join(string $table, string $condition, string $alias = '', string $type = 'INNER'): self`
Add a JOIN clause.

#### `leftJoin(string $table, string $condition, string $alias = ''): self`
Add a LEFT JOIN clause.

#### `innerJoin(string $table, string $condition, string $alias = ''): self`
Add an INNER JOIN clause.

#### `groupBy(string $groupBy): self`
Add GROUP BY clause.

#### `orderBy(string $orderBy): self`
Add ORDER BY clause.

#### `limit(int $limit, int $offset = 0): self`
Add LIMIT and optional OFFSET.

#### `count(string $column = '*'): self`
Set the query action to COUNT.

#### `insert(array $data): self`
Set the query action to INSERT with data.

#### `update(array $data): self`
Set the query action to UPDATE with data.

#### `delete(): self`
Set the query action to DELETE.

#### `build(): array`
Build and return the query as `['sql' => string, 'params' => array]`.

#### `get(): array`
Alias for `build()`.

#### `buildSQL(): string`
Build and return only the SQL string (for SELECT queries).

#### `getParams(): array`
Get the parameter array for binding.

## Advanced Examples

### Complex JOIN with Multiple Conditions

```php
$q = Builder::table('orders')
    ->alias('o')
    ->select([
        'o.id',
        'o.total',
        'u.name AS customer_name',
        'p.title AS product_title'
    ])
    ->innerJoin('users', 'o.user_id = u.id', 'u')
    ->leftJoin('order_items', 'o.id = oi.order_id', 'oi')
    ->leftJoin('products', 'oi.product_id = p.id', 'p')
    ->where([
        'o.status' => 'completed',
        'o.total' => ['>=', 100],
        'o.created_at' => ['>=', '2024-01-01']
    ])
    ->groupBy('o.id')
    ->orderBy('o.created_at DESC')
    ->limit(50)
    ->build();
```

### Dynamic Query Building

```php
$query = Builder::table('products')->alias('p');

// Conditionally add conditions
if (!empty($categoryId)) {
    $query->where(['p.category_id' => $categoryId]);
}

if (!empty($minPrice)) {
    $query->where(['p.price' => ['>=', $minPrice]]);
}

if (!empty($searchTerm)) {
    $query->where(['p.name' => ['LIKE', "%{$searchTerm}%"]]);
}

// Add sorting
$query->orderBy('p.created_at DESC')->limit(20);

$result = $query->build();
```

### Batch Insert Helper

```php
function batchInsert($pdo, $table, array $rows) {
    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            $q = Builder::table($table)->insert($row)->build();
            $stmt = $pdo->prepare($q['sql']);
            $stmt->execute($q['params']);
        }
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Usage
$users = [
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
    ['name' => 'Charlie', 'email' => 'charlie@example.com']
];

batchInsert($pdo, 'users', $users);
```

## Security

This library uses **prepared statements with parameter binding** to protect against SQL injection attacks. Parameters are never directly concatenated into SQL strings.

**Important:** Use `raw()` only with trusted data or SQL functions. Never use `raw()` with user input:

```php
// ✅ SAFE - Using parameter binding
$q = Builder::table('users')
    ->where(['email' => $_POST['email']])
    ->build();

// ✅ SAFE - Using raw() with SQL functions
$q = Builder::table('users')
    ->update(['updated_at' => Builder::raw('NOW()')])
    ->build();

// ❌ DANGEROUS - Never do this!
$q = Builder::table('users')
    ->where(['email' => Builder::raw("'{$_POST['email']}'")]) // SQL injection risk!
    ->build();
```

## Debugging with Tracy

GenerateQuery provides automatic Tracy Debugger integration with a beautiful custom panel. **No setup required!** Just install Tracy and use GenerateQuery - the debug panel will automatically appear.

### Automatic Setup

```php
use Tracy\Debugger;
use KnifeLemon\EasyQuery\Builder;

// Enable Tracy (development only)
Debugger::enable();

// That's it! Just use GenerateQuery normally
$q = Builder::table('users')
    ->select(['id', 'name', 'email'])
    ->where(['status' => 'active'])
    ->orderBy('created_at DESC')
    ->limit(10)
    ->build();

// All queries are automatically logged to Tracy panel
// No manual initialization needed!
```

### How It Works

- **Auto-initialization**: First `Builder` instantiation automatically initializes Tracy logging
- **Zero configuration**: Just have Tracy installed and it works
- **Automatic logging**: Every `build()` call is logged to the custom Tracy panel

### Tracy Panel Features

The custom Tracy panel shows:

- **Summary Cards**: Total queries, breakdown by type (SELECT, INSERT, UPDATE, DELETE, COUNT)
- **Query List**: Each query with:
  - Action type badge (color-coded)
  - Generated SQL (syntax highlighted)
  - Parameters array
  - Expandable details (table, where, joins, order, limit, etc.)
  - Timestamp

**Install Tracy:**
```bash
composer require tracy/tracy
```

If Tracy is not installed, GenerateQuery works normally without any debug output.

## Testing

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer phpstan
```

## Requirements

- PHP >= 7.4
- PDO or MySQLi extension (for database connectivity)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Credits

Created and maintained by [KnifeLemon](https://github.com/knifelemon)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## Support

If you encounter any issues or have questions, please [open an issue](https://github.com/knifelemon/EasyQueryBuilder/issues) on GitHub.

## Resources

- [FlightPHP Framework](https://flightphp.com/)
- [FlightPHP GitHub](https://github.com/flightphp/core)
- [FlightPHP SimplePdo Documentation](https://docs.flightphp.com/learn/simple-pdo)
