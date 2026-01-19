# Quick Start Guide

Get started with EasyQuery in 5 minutes!

## Installation

```bash
composer require knifelemon/easy-query
```

## Basic Usage

### 1. Set up your database connection

```php
use KnifeLemon\EasyQuery\Builder;

// Create PDO connection
$pdo = new PDO('mysql:host=localhost;dbname=myapp', 'user', 'pass');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
```

### 2. Build your first query

```php
// Build a SELECT query
$q = Builder::table('users')
    ->select(['id', 'name', 'email'])
    ->where(['status' => 'active'])
// Execute
$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

## Common Patterns

### INSERT

```php
$q = Builder::table('users')
    ->insert([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'created_at' => Builder::raw('NOW()')
    ])
    ->build();

$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);
$userId = $pdo->lastInsertId();
```

### UPDATE

```php
$q = Builder::table('users')
    ->update(['status' => 'inactive'])
    ->where(['id' => 123])
    ->build();

$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);
$affectedRows = $stmt->rowCount();
```

### DELETE

```php
$q = Builder::table('users')
    ->delete()
    ->where(['id' => 123])
    ->build();

$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);
```

### JOIN

```php
$q = Builder::table('posts')
    ->alias('p')
    ->select(['p.title', 'u.name AS author'])
    ->innerJoin('users', 'p.user_id = u.id', 'u')
    ->where(['p.status' => 'published'])
    ->build();

$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### COUNT

```php
$q = Builder::table('users')
    ->count()
    ->where(['status' => 'active'])
    ->build();

$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $result['cnt'];
```

### NOT IN

```php
$q = Builder::table('users')
    ->where(['status' => ['NOT IN', ['banned', 'deleted']]])
    ->build();

$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### Safe Column Names from User Input

```php
// When sorting by user-selected column
$sortColumn = Builder::safeIdentifier($_GET['sort']);  // Validates input
$q = Builder::table('users')
    ->orderBy($sortColumn . ' DESC')
    ->build();

// For aggregation with user-selected column
$q = Builder::table('orders')
    ->select([Builder::rawSafe('SUM({col})', ['col' => $_GET['column']])->value])
    ->build();
```

## FlightPHP Integration

```php
use KnifeLemon\EasyQuery\Builder;

Flight::route('GET /users', function() {
    $q = Builder::table('users')
        ->where(['status' => 'active'])
        ->orderBy('created_at DESC')
        ->limit(20)
        ->build();
    
    $users = Flight::db()->fetchAll($q['sql'], $q['params']);
    Flight::json(['users' => $users]);
});
```

## Next Steps

- Read the full [README.md](README.md) for comprehensive documentation
- Check out the [examples](examples/) directory for more patterns
- Review [SECURITY.md](SECURITY.md) for security best practices

## Need Help?

- [Open an issue](https://github.com/knifelemon/EasyQueryBuilder/issues)
- Read the [Contributing Guide](CONTRIBUTING.md)
- Check [Security Policy](SECURITY.md)

Happy querying! 🚀
