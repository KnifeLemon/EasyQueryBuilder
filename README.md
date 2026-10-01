# EasyQuery

[![Latest Stable Version](https://poser.pugx.org/knifelemon/easy-query/v/stable)](https://packagist.org/packages/knifelemon/easy-query)
[![Total Downloads](https://poser.pugx.org/knifelemon/easy-query/downloads)](https://packagist.org/packages/knifelemon/easy-query)
[![Latest Unstable Version](https://poser.pugx.org/knifelemon/easy-query/v/unstable)](https://packagist.org/packages/knifelemon/easy-query)
[![License](https://poser.pugx.org/knifelemon/easy-query/license)](https://packagist.org/packages/knifelemon/easy-query)
[![PHP Version Require](https://poser.pugx.org/knifelemon/easy-query/require/php)](https://packagist.org/packages/knifelemon/easy-query)

A lightweight, fluent PHP SQL query builder that generates SQL and parameters. Designed to work with any database connection (PDO, MySQLi, FlightPHP SimplePdo).

## Features

- 🔗 **Fluent API** - Chain methods for readable query construction
- 🛡️ **SQL Injection Protection** - Automatic parameter binding with prepared statements
- 🔧 **Raw SQL Support** - Insert raw SQL expressions with `raw()`
- 📝 **Multiple Query Types** - SELECT, INSERT, UPDATE, DELETE, COUNT
- 🔀 **JOIN Support** - INNER, LEFT, RIGHT joins with aliases
- 🎯 **Advanced Conditions** - LIKE, IN, BETWEEN, comparison operators, grouped conditions
- 📊 **Aggregates** - COUNT, SUM, AVG, MIN, MAX with DISTINCT and subqueries
- 🧮 **Batch Writes** - insertBatch, updateBatch, upsertBatch, deleteBatch
- 🪄 **Conditional Chaining** - `when()` / `whenNot()` for readable dynamic queries
- 🌐 **Database Agnostic** - Returns SQL + params, use with any DB connection
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
// Method 1: Set alias in table() method (v1.0.2.2+)
$q = Builder::table('users', 'u')
    ->select(['u.id', 'u.name'])
    ->where(['u.status' => 'active'])
    ->orderBy('u.created_at DESC')
    ->limit(10)
    ->build();

// Method 2: Set alias using alias() method
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
```

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
```

#### NOT IN Operator

```php
$q = Builder::table('users')
    ->where([
        'status' => ['NOT IN', ['banned', 'deleted', 'suspended']]
    ])
    ->build();

// Result:
// sql: "SELECT * FROM users WHERE status NOT IN (?, ?, ?)"
// params: ['banned', 'deleted', 'suspended']
```

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
```

#### IS NULL and IS NOT NULL

```php
// IS NULL - check for NULL values
$q = Builder::table('users')
    ->where(['deleted_at' => ['IS', null]])
    ->build();

// Result:
// sql: "SELECT * FROM users WHERE deleted_at IS NULL"
// params: []
```

```php
// IS NOT NULL - check for non-NULL values
$q = Builder::table('users')
    ->where(['email' => ['IS NOT', null]])
    ->build();

// Result:
// sql: "SELECT * FROM users WHERE email IS NOT NULL"
// params: []
```

```php
// Mixed with other conditions
$q = Builder::table('users')
    ->where([
        'kakao_sender_key' => ['IS NOT', null],
        'is_delete' => 'N',
        'status' => 'active'
    ])
    ->build();

// Result:
// sql: "SELECT * FROM users WHERE kakao_sender_key IS NOT NULL AND is_delete = ? AND status = ?"
// params: ['N', 'active']
```

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
```

#### IN / NOT IN Methods

```php
// IN with an array of values
$q = Builder::table('users')
    ->where(['status' => 'active'])
    ->whereIn('id', [1, 2, 3, 4, 5])
    ->build();
// sql: "SELECT * FROM users WHERE status = ? AND id IN (?, ?, ?, ?, ?)"
// params: ['active', 1, 2, 3, 4, 5]

// IN with a subquery
$q = Builder::table('users')
    ->whereIn('id', Builder::table('logs')->select('user_id')->where(['type' => 'signup']))
    ->build();
// sql: "SELECT * FROM users WHERE id IN (SELECT user_id FROM logs WHERE type = ?)"
// params: ['signup']

// NOT IN
$q = Builder::table('users')
    ->whereNotIn('status', ['banned', 'deleted'])
    ->build();
// sql: "SELECT * FROM users WHERE status NOT IN (?, ?)"
// params: ['banned', 'deleted']
```

#### LIKE / NOT LIKE

```php
// LIKE with escaped wildcards and an explicit ESCAPE clause
$q = Builder::table('products')
    ->where(['category' => 'books'])
    ->like('title', '100%')
    ->build();
// sql: "SELECT * FROM products WHERE category = ? AND title LIKE ? ESCAPE '!'"
// params: ['books', '%100!%%']

// Search only the start of the value
$q = Builder::table('products')
    ->like('title', 'PHP', 'after')
    ->build();
// sql: "SELECT * FROM products WHERE title LIKE ? ESCAPE '!'"
// params: ['PHP%']

// NOT LIKE
$q = Builder::table('products')
    ->notLike('title', 'expired')
    ->build();
// sql: "SELECT * FROM products WHERE title NOT LIKE ? ESCAPE '!'"
// params: ['%expired%']
```

#### Grouped Conditions

```php
// AND group (nested parentheses)
$q = Builder::table('orders')
    ->where(['status' => 'open'])
    ->groupStart()
        ->where(['total' => ['>=', 100]])
        ->where(['priority' => 'high'])
    ->groupEnd()
    ->build();
// sql: "SELECT * FROM orders WHERE status = ? AND (total >= ? AND priority = ?)"
// params: ['open', 100, 'high']

// OR alternation via orWhere() (conditions within the same call are joined with OR)
$q = Builder::table('orders')
    ->where(['status' => 'open'])
    ->where(['total' => ['>=', 100]])
    ->orWhere(['priority' => 'high', 'urgent' => 1])
    ->build();
// sql: "SELECT * FROM orders WHERE status = ? AND total >= ? AND (priority = ? OR urgent = ?)"
// params: ['open', 100, 'high', 1]

// OR group via orGroupStart()
$q = Builder::table('users')
    ->where(['status' => 'active'])
    ->orGroupStart()
        ->where(['role' => 'admin'])
        ->where(['plan' => 'premium'])
    ->groupEnd()
    ->build();
// sql: "SELECT * FROM users WHERE status = ? OR (role = ? AND plan = ?)"
// params: ['active', 'admin', 'premium']

// Negated group
$q = Builder::table('users')
    ->where(['status' => 'active'])
    ->notGroupStart()
        ->where(['role' => 'guest'])
        ->where(['banned' => 1])
    ->groupEnd()
    ->build();
// sql: "SELECT * FROM users WHERE status = ? AND NOT (role = ? AND banned = ?)"
// params: ['active', 'guest', 1]
```

### HAVING

```php
$q = Builder::table('orders')
    ->select(['user_id', 'total'])
    ->groupBy('user_id')
    ->having(['total' => ['>', 1000]])
    ->build();
// sql: "SELECT user_id, total FROM orders GROUP BY user_id HAVING total > ?"
// params: [1000]
```

### Aggregate Functions

```php
$q = Builder::table('orders')
    ->selectCount('*', 'total_orders')
    ->selectSum('amount', 'total_amount')
    ->where(['status' => 'paid'])
    ->build();
// sql: "SELECT COUNT(*) AS total_orders, SUM(amount) AS total_amount FROM orders WHERE status = ?"
// params: ['paid']

$q = Builder::table('orders')
    ->select(['user_id'])
    ->selectAvg('amount')
    ->selectMin('amount', 'min_amount')
    ->selectMax('amount', 'max_amount')
    ->groupBy('user_id')
    ->build();
// sql: "SELECT user_id, AVG(amount), MIN(amount) AS min_amount, MAX(amount) AS max_amount FROM orders GROUP BY user_id"
// params: []
```

### DISTINCT

```php
$q = Builder::table('users')
    ->distinct()
    ->select(['country'])
    ->build();
// sql: "SELECT DISTINCT country FROM users"
// params: []
```

### Subqueries

```php
// Subquery in the SELECT list
$q = Builder::table('users')
    ->select(['id', 'name'])
    ->selectSubquery(
        Builder::table('orders')->selectCount('*')->where(['user_id' => 42]),
        'order_count'
    )
    ->build();
// sql: "SELECT id, name, (SELECT COUNT(*) FROM orders WHERE user_id = ?) AS order_count FROM users"
// params: [42]

// Subquery as the FROM source
$q = Builder::table('users')
    ->fromSubquery(
        Builder::table('users')->select(['id', 'email'])->where(['status' => 'active']),
        'u'
    )
    ->where(['u.age' => ['>=', 18]])
    ->build();
// sql: "SELECT * FROM (SELECT id, email FROM users WHERE status = ?) AS u WHERE u.age >= ?"
// params: ['active', 18]
```

### UNION

```php
$q = Builder::table('active_users')
    ->select(['id', 'name'])
    ->union(Builder::table('vip_users')->select(['id', 'name']))
    ->build();
// sql: "SELECT id, name FROM active_users UNION SELECT id, name FROM vip_users"
// params: []

// UNION ALL keeps duplicate rows
$q = Builder::table('jan_orders')
    ->unionAll(Builder::table('feb_orders'))
    ->build();
// sql: "SELECT * FROM jan_orders UNION ALL SELECT * FROM feb_orders"
// params: []
```

### Batch Writes

```php
// Multi-row INSERT
$q = Builder::table('users')
    ->insertBatch([
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        ['name' => 'Bob', 'email' => 'bob@example.com'],
    ])
    ->build();
// sql: "INSERT INTO users (name, email) VALUES (?, ?), (?, ?)"
// params: ['Alice', 'alice@example.com', 'Bob', 'bob@example.com']

// Upsert with ON DUPLICATE KEY UPDATE
$q = Builder::table('user_stats')
    ->upsertBatch([
        ['user_id' => 1, 'views' => 10],
        ['user_id' => 2, 'views' => 5],
    ], ['user_id'])
    ->build();
// sql: "INSERT INTO user_stats (user_id, views) VALUES (?, ?), (?, ?) ON DUPLICATE KEY UPDATE views = VALUES(views)"
// params: [1, 10, 2, 5]

// Multi-row UPDATE via CASE WHEN
$q = Builder::table('users')
    ->updateBatch([
        ['id' => 1, 'status' => 'active'],
        ['id' => 2, 'status' => 'disabled'],
    ], 'id')
    ->build();
// sql: "UPDATE users SET status = CASE WHEN id = ? THEN ? WHEN id = ? THEN ? END WHERE id IN (?, ?)"
// params: [1, 'active', 2, 'disabled', 1, 2]

// Batch DELETE
$q = Builder::table('users')
    ->deleteBatch('id', [1, 2, 3])
    ->build();
// sql: "DELETE FROM users WHERE id IN (?, ?, ?)"
// params: [1, 2, 3]
```

### Conditional Chaining

```php
$q = Builder::table('products')
    ->when(!empty($categoryId), function ($query) use ($categoryId) {
        $query->where(['category_id' => $categoryId]);
    })
    ->when(!empty($searchTerm), function ($query) use ($searchTerm) {
        $query->like('name', $searchTerm);
    })
    ->build();
```

### Builder Reuse with build(true)

```php
// Reset a builder in one call (replaces manual clear*() calls)
$q = Builder::table('users')
    ->where(['status' => 'active'])
    ->build(true);
// $q is the query result; the builder is reset and ready for the next statement
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

#### INSERT with ON DUPLICATE KEY UPDATE

Use `onDuplicateKeyUpdate()` to handle duplicate key errors gracefully (MySQL/MariaDB only):

```php
// Basic usage - update specific columns on duplicate
$q = Builder::table('users')
    ->insert([
        'email' => 'user@example.com',
        'name' => 'John Doe',
        'points' => 100
    ])
    ->onDuplicateKeyUpdate([
        'name' => 'John Doe Updated',
        'points' => 200
    ])
    ->build();

// Result:
// sql: "INSERT INTO users SET email = ?, name = ?, points = ? ON DUPLICATE KEY UPDATE name = ?, points = ?"
// params: ['user@example.com', 'John Doe', 100, 'John Doe Updated', 200]
```

```php
// Increment values on duplicate using raw SQL
$q = Builder::table('users')
    ->insert([
        'email' => 'user@example.com',
        'name' => 'John Doe',
        'points' => 100
    ])
    ->onDuplicateKeyUpdate([
        'points' => Builder::raw('points + 100'),
        'login_count' => Builder::raw('login_count + 1'),
        'updated_at' => Builder::raw('NOW()')
    ])
    ->build();

// Result:
// sql: "INSERT INTO users SET email = ?, name = ?, points = ? ON DUPLICATE KEY UPDATE points = points + 100, login_count = login_count + 1, updated_at = NOW()"
// params: ['user@example.com', 'John Doe', 100]
```

```php
// Use VALUES() to reference the inserted value
$q = Builder::table('user_stats')
    ->insert([
        'user_id' => 123,
        'views' => 10,
        'clicks' => 5
    ])
    ->onDuplicateKeyUpdate([
        'views' => Builder::raw('views + VALUES(views)'),
        'clicks' => Builder::raw('clicks + VALUES(clicks)'),
        'updated_at' => Builder::raw('NOW()')
    ])
    ->build();

// Result:
// sql: "INSERT INTO user_stats SET user_id = ?, views = ?, clicks = ? ON DUPLICATE KEY UPDATE views = views + VALUES(views), clicks = clicks + VALUES(clicks), updated_at = NOW()"
// params: [123, 10, 5]
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

#### Raw SQL with Bindings

When you need parameterized values in raw expressions:

```php
// Raw expression with bound parameters
$q = Builder::table('orders')
    ->update([
        'total' => Builder::raw('COALESCE(subtotal, ?) + ?', [0, 10])
    ])
    ->where(['id' => 1])
    ->build();

// Result:
// sql: "UPDATE orders SET total = COALESCE(subtotal, ?) + ? WHERE id = ?"
// params: [0, 10, 1]
```

#### Safe Identifiers for User Input

When column names come from user input (e.g., dynamic sorting), use `safeIdentifier()` to prevent SQL injection:

```php
// Validate user-provided column name
$sortColumn = $_GET['sort'];  // e.g., 'created_at'
$safeColumn = Builder::safeIdentifier($sortColumn);

$q = Builder::table('users')
    ->orderBy($safeColumn . ' DESC')
    ->build();

// If user tries: "name; DROP TABLE users--"
// Throws InvalidArgumentException: Invalid identifier
```

#### Safe Raw Expressions with User Input

Use `rawSafe()` when building raw SQL with user-provided column names:

```php
// User selects which column to aggregate
$userColumn = $_GET['aggregate_column'];  // e.g., 'total_amount'

$q = Builder::table('orders')
    ->select([
        Builder::rawSafe('COALESCE(SUM({col}), ?)', ['col' => $userColumn], [0])->value . ' AS total'
    ])
    ->build();

// Result (with safe column):
// sql: "SELECT COALESCE(SUM(total_amount), ?) AS total FROM orders"
// params: [0]

// If user tries SQL injection, throws InvalidArgumentException
```

```php
// Multiple safe identifiers
use KnifeLemon\EasyQuery\BuilderRaw;

$raw = BuilderRaw::withIdentifiers(
    '{table}.{col1} + {table}.{col2}',
    ['table' => 'orders', 'col1' => 'price', 'col2' => 'tax']
);
// Result: "orders.price + orders.tax"
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

#### `Builder::table(string $table, string $alias = ''): Builder`
Set the table name for the query. Optionally set a table alias in the same call.

#### `Builder::raw(string $value): BuilderRaw`
Create a raw SQL expression that will be inserted directly without parameter binding.

#### `Builder::raw(string $value, array $bindings = []): BuilderRaw`
Create a raw SQL expression with optional bound parameters for `?` placeholders.

#### `Builder::rawSafe(string $expression, array $identifiers, array $bindings = []): BuilderRaw`
Create a raw SQL expression with safe identifier substitution. Use `{placeholder}` syntax for identifiers.

#### `Builder::safeIdentifier(string $identifier): string`
Validate and return a safe column/table identifier. Only allows alphanumeric, underscores, and dots.

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

#### `orderBy(string $column, ?string $direction = null): self`
Add ORDER BY. Without a direction, `$orderBy` is treated as a full sort expression (e.g. `created_at DESC`). With a direction, the column is validated with `safeIdentifier()` and the direction must be `ASC` or `DESC`, making it safe for user input.

#### `limit(int $limit, int $offset = 0): self`
Add LIMIT and optional OFFSET.

#### `distinct(): self`
Add `DISTINCT` to the SELECT query.

#### `selectSubquery(Builder $query, string $alias): self`
Add a subquery as a SELECT column with an alias.

#### `selectCount(string $column, string $alias = ''): self`
Add `COUNT(column)` to the SELECT list.

#### `selectSum(string $column, string $alias = ''): self`
Add `SUM(column)` to the SELECT list.

#### `selectAvg(string $column, string $alias = ''): self`
Add `AVG(column)` to the SELECT list.

#### `selectMin(string $column, string $alias = ''): self`
Add `MIN(column)` to the SELECT list.

#### `selectMax(string $column, string $alias = ''): self`
Add `MAX(column)` to the SELECT list.

#### `fromSubquery(Builder $query, string $alias): self`
Use a subquery as the FROM source instead of the table.

#### `whereIn(string $column, array $values): self`
Add a `WHERE column IN (...)` condition.

#### `whereIn(string $column, Builder $query): self`
Add a `WHERE column IN (subquery)` condition with correctly ordered parameters.

#### `orWhereIn(string $column, array|Builder $values): self`
Add an OR `IN` condition (array or subquery).

#### `whereNotIn(string $column, array|Builder $values): self`
Add a `WHERE column NOT IN (...)` condition (array or subquery).

#### `orWhereNotIn(string $column, array|Builder $values): self`
Add an OR `NOT IN` condition (array or subquery).

#### `like(string $column, string $value, string $position = 'both'): self`
Add a `WHERE column LIKE ?` condition. Values are bound as parameters and escaped with an explicit `ESCAPE '!'` clause. `$position` is one of `'before'`, `'after'`, `'both'`, `'none'`.

#### `orLike(string $column, string $value, string $position = 'both'): self`
Add an OR `LIKE` condition.

#### `notLike(string $column, string $value, string $position = 'both'): self`
Add a `WHERE column NOT LIKE ?` condition.

#### `orNotLike(string $column, string $value, string $position = 'both'): self`
Add an OR `NOT LIKE` condition.

#### `groupStart(): self`
Open a new condition group joined with AND. Must be closed with `groupEnd()`.

#### `orGroupStart(): self`
Open a new condition group joined with OR. Must be closed with `groupEnd()`.

#### `notGroupStart(): self`
Open a negated condition group (`NOT (...)`). Must be closed with `groupEnd()`.

#### `groupEnd(): self`
Close an open condition group.

#### `having(array $conditions): self`
Add HAVING conditions for filtered aggregate queries.

#### `orHaving(array $conditions): self`
Add OR HAVING conditions.

#### `rightJoin(string $table, string $condition, string $alias = ''): self`
Add a RIGHT JOIN clause.

#### `union(Builder $query): self`
Add a UNION to the query.

#### `unionAll(Builder $query): self`
Add a UNION ALL to the query.

#### `insertBatch(array $rows): self`
Set the query action to a multi-row INSERT. All rows must contain the same columns. Raw `Builder::raw()` values are inlined.

#### `upsertBatch(array $rows, array $uniqueKeys): self`
Set the query action to INSERT ... ON DUPLICATE KEY UPDATE (MySQL/MariaDB). `$uniqueKeys` are the columns that define a duplicate row; remaining columns are updated with `VALUES(column)`.

#### `updateBatch(array $rows, string $whereColumn): self`
Set the query action to a multi-row UPDATE using CASE WHEN blocks. The `$whereColumn` value identifies each row and is also used in the final `WHERE ... IN (...)` clause.

#### `deleteBatch(string $whereColumn, array $values): self`
Set the query action to DELETE WHERE column IN (...).

#### `when($condition, callable $callback): self`
Conditionally apply query modifications. If `$condition` is truthy, `$callback($this)` is invoked. Always returns `$this` and leaves the builder usable.

#### `whenNot($condition, callable $callback): self`
Inverse of `when()`. The callback runs when the condition is falsy.

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

#### `build(bool $reset = false): array`
Build and return the query as `['sql' => string, 'params' => array]`. Pass `true` to reset the builder afterwards (opt-in; the builder is not reset by default). The reset clears the conditions and returns the action to `SELECT`, so the builder can be reused for any query type; the table and alias are preserved. Note this differs from `clearAll()`, which is called during the reset but deliberately preserves the action (existing behavior); `build(true)` resets it as an extra step.

#### `get(): array`
Alias for `build()`.

#### `buildSQL(): string`
Build and return only the SQL string (for SELECT queries).

#### `getSQL(): string`
Alias for `buildSQL()`.

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

### Safe Parameter Binding

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
    ->where(['email' => Builder::raw("'{$_POST['email']}'")])  // SQL injection risk!
    ->build();
```

### Safe Column Names from User Input

When column names come from user input (e.g., sorting, aggregation), use these safety methods:

```php
// ✅ SAFE - Validate column name
$sortColumn = Builder::safeIdentifier($_GET['sort']);
$q = Builder::table('users')->orderBy($sortColumn . ' DESC')->build();

// ✅ SAFE - Safe raw expression with user column
$q = Builder::table('orders')
    ->select([Builder::rawSafe('SUM({col})', ['col' => $_GET['column']])->value])
    ->build();

// ❌ DANGEROUS - Never concatenate user input in raw()
$q = Builder::table('orders')
    ->select([Builder::raw("SUM({$_GET['column']})")])
    ->build();
```

### Allowed Identifier Characters

`safeIdentifier()` and `rawSafe()` only allow:
- Letters (a-z, A-Z)
- Numbers (0-9)
- Underscores (_)
- Dots (.) for table.column notation

Any other characters will throw an `InvalidArgumentException`.

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
