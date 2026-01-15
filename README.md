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

## Understanding build() Return Value

The `build()` method returns an array with two keys: `sql` and `params`. This separation is fundamental to how EasyQuery keeps your database safe.

### What You Get

```php
$q = Builder::table('users')
    ->where(['email' => 'user@example.com'])
    ->build();

// Returns:
// [
//     'sql' => 'SELECT * FROM users WHERE email = ?',
//     'params' => ['user@example.com']
// ]
```

### Why Split SQL and Parameters?

EasyQuery uses **prepared statements** - a security feature that prevents SQL injection attacks. Instead of inserting values directly into SQL (which is dangerous), we:

1. **Generate SQL with placeholders (`?`)** - The SQL structure is defined first
2. **Keep values separate** - User data stays in the `params` array
3. **Let the database combine them safely** - Your database driver (PDO, MySQLi) securely binds parameters

### How to Use

The most common pattern is:

```php
// 1. Build your query
$q = Builder::table('users')
    ->where(['status' => 'active'])
    ->limit(10)
    ->build();

// 2. Prepare the SQL statement
$stmt = $pdo->prepare($q['sql']);

// 3. Execute with parameters
$stmt->execute($q['params']);

// 4. Get results
$users = $stmt->fetchAll();
```

### Why This Matters

**❌ Dangerous (Never do this):**
```php
// Direct concatenation = SQL injection vulnerability!
$email = $_POST['email'];
$sql = "SELECT * FROM users WHERE email = '$email'";
// If $email is: ' OR '1'='1
// SQL becomes: SELECT * FROM users WHERE email = '' OR '1'='1'
// This returns ALL users!
```

**✅ Safe (EasyQuery way):**
```php
$email = $_POST['email'];
$q = Builder::table('users')
    ->where(['email' => $email])
    ->build();
// SQL: SELECT * FROM users WHERE email = ?
// Params: ['user input']
// The database treats the input as data, not code
```

### Working with Different Frameworks

EasyQuery's separation of SQL and parameters makes it compatible with any database library:

```php
// PDO
$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);

// MySQLi
$stmt = $mysqli->prepare($q['sql']);
$stmt->execute($q['params']);

// FlightPHP SimplePdo
$users = Flight::db()->fetchAll($q['sql'], $q['params']);
```

This universal approach means you can use EasyQuery with any framework or custom database setup.

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

// Result:
// sql: "SELECT u.id, u.name FROM users AS u WHERE u.status = ? ORDER BY u.created_at DESC LIMIT 10"
// params: ['active']

#### SELECT with JOIN

```php
$q = Builder::table('users')
    ->alias('u')
    ->select(['u.id', 'u.name', 'p.title', 'p.content'])
    ->innerJoin('posts', 'u.id = p.user_id', 'p')
    ->where(['u.status' => 'active'])
    ->orderBy('p.published_at DESC')
    ->build();

// Result:
// sql: "SELECT u.id, u.name, p.title, p.content FROM users AS u INNER JOIN posts AS p ON u.id = p.user_id WHERE u.status = ? ORDER BY p.published_at DESC"
// params: ['active']

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

// Result:
// sql: "SELECT * FROM users WHERE age >= ? AND score < ? AND name LIKE ?"
// params: [18, 100, '%john%']

#### IN Operator

```php
$q = Builder::table('users')
    ->where([
        'id' => ['IN', [1, 2, 3, 4, 5]]
    ])
    ->build();

// Result:
// sql: "SELECT * FROM users WHERE id IN (?, ?, ?, ?, ?)"
// params: [1, 2, 3, 4, 5]

#### BETWEEN Operator

```php
$q = Builder::table('products')
    ->where([
        'price' => ['BETWEEN', [100, 500]]
    ])
    ->build();

// Result:
// sql: "SELECT * FROM products WHERE price BETWEEN ? AND ?"
// params: [100, 500]

#### OR Conditions

Use `orWhere()` to add OR grouped conditions. Conditions within the same `orWhere()` call are joined with OR, and each group is added to the main query with AND.

```php
// Simple OR condition
$q = Builder::table('users')
    ->where(['status' => 'active'])
    ->orWhere(['role' => 'admin'])
    ->build();
// WHERE status = ? AND (role = ?)
// params: ['active', 'admin']

// Multiple conditions in OR group
$q = Builder::table('users')
    ->where(['status' => 'active'])
    ->orWhere([
        'role' => 'admin',
        'role' => 'moderator',
        'permissions' => ['LIKE', '%manage%']
    ])
    ->build();
// WHERE status = ? AND (role = ? OR role = ? OR permissions LIKE ?)
// params: ['active', 'admin', 'moderator', '%manage%']

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

// Result:
// sql: "SELECT * FROM products WHERE price > (SELECT AVG(price) FROM products)"
// params: []
```

## Framework Integration

### FlightPHP Integration

EasyQuery works with [FlightPHP](https://flightphp.com/)'s SimplePdo by generating SQL and parameters that you pass directly to SimplePdo methods.

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

#### `clearWhere(): self`
Clear WHERE conditions and parameters (allows query builder reuse).

#### `clearSelect(): self`
Clear SELECT columns (reset to default '*').

#### `clearJoin(): self`
Clear all JOIN clauses.

#### `clearGroupBy(): self`
Clear GROUP BY clause.

#### `clearOrderBy(): self`
Clear ORDER BY clause.

#### `clearLimit(): self`
Clear LIMIT and OFFSET.

#### `clearAll(): self`
Clear all query conditions (reset builder to initial state).

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

### Query Builder Reuse

The query builder can be reused by clearing specific conditions or resetting entirely. This is useful when you need to execute similar queries with different parameters.

```php
// Create a base query
$baseQuery = Builder::table('users')
    ->select(['id', 'name', 'email'])
    ->where(['status' => 'active'])
    ->orderBy('created_at DESC');

// First query: Active users in the last 30 days
$q1 = $baseQuery
    ->where(['created_at' => ['>=', date('Y-m-d', strtotime('-30 days'))]])
    ->limit(10)
    ->build();

$recentUsers = executeQuery($q1);

// Clear WHERE to reuse the builder
$baseQuery->clearWhere();

// Second query: All active premium users
$q2 = $baseQuery
    ->where(['status' => 'active', 'plan' => 'premium'])
    ->limit(20)
    ->build();

$premiumUsers = executeQuery($q2);

// Clear specific parts
$baseQuery
    ->clearSelect()
    ->clearOrderBy()
    ->clearLimit();

// Third query: Count active users
$q3 = $baseQuery
    ->count()
    ->where(['status' => 'active'])
    ->build();

$activeCount = executeQuery($q3);
```

#### Clear Methods Usage

```php
// Clear only WHERE conditions
$query->clearWhere();

// Clear only SELECT columns
$query->clearSelect();

// Clear only JOINs
$query->clearJoin();

// Clear only ORDER BY
$query->clearOrderBy();

// Clear only GROUP BY
$query->clearGroupBy();

// Clear only LIMIT and OFFSET
$query->clearLimit();

// Clear everything and start fresh
$query->clearAll();
```

#### Practical Example: Pagination with Reuse

```php
// Base query for user list
$usersQuery = Builder::table('users')
    ->select(['id', 'name', 'email', 'created_at'])
    ->where(['status' => 'active'])
    ->orderBy('created_at DESC');

// Get total count
$countQuery = clone $usersQuery;
$countResult = $countQuery
    ->clearSelect()
    ->count()
    ->build();

$totalUsers = executeQuery($countResult)[0]['cnt'];

// Get paginated results
$page = 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$listResult = $usersQuery
    ->limit($perPage, $offset)
    ->build();

$users = executeQuery($listResult);

// Next page - reuse the same query
$usersQuery->clearLimit();
$page = 2;
$offset = ($page - 1) * $perPage;

$nextPageResult = $usersQuery
    ->limit($perPage, $offset)
    ->build();

$nextPageUsers = executeQuery($nextPageResult);
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

EasyQuery provides automatic Tracy Debugger integration with a beautiful custom panel. **No setup required!** Just install Tracy and use EasyQuery - the debug panel will automatically appear.

### Automatic Setup

```php
use Tracy\Debugger;
use KnifeLemon\EasyQuery\Builder;

// Enable Tracy (development only)
Debugger::enable();

// That's it! Just use EasyQuery normally
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

If Tracy is not installed, EasyQuery works normally without any debug output.

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
