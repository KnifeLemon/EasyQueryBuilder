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
use KnifeLemon\EasyQuery\PDOAdapter;

// Create PDO connection
$pdo = new PDO('mysql:host=localhost;dbname=myapp', 'user', 'pass');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Optional: Use adapter for cleaner code
$db = new PDOAdapter($pdo);
```

### 2. Build your first query

```php
// Build a SELECT query
$q = Builder::table('users')
    ->select(['id', 'name', 'email'])
    ->where(['status' => 'active'])
    ->limit(10)
    ->build();

// Execute
$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### 3. With adapter (cleaner)

```php
$q = Builder::table('users')
    ->select(['id', 'name', 'email'])
    ->where(['status' => 'active'])
    ->limit(10)
    ->build();

$users = $db->fetchAll($q);
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

$db->execute($q);
$userId = $db->lastInsertId();
```

### UPDATE

```php
$q = Builder::table('users')
    ->update(['status' => 'inactive'])
    ->where(['id' => 123])
    ->build();

$affectedRows = $db->execute($q);
```

### DELETE

```php
$q = Builder::table('users')
    ->delete()
    ->where(['id' => 123])
    ->build();

$db->execute($q);
```

### JOIN

```php
$q = Builder::table('posts')
    ->alias('p')
    ->select(['p.title', 'u.name AS author'])
    ->innerJoin('users', 'p.user_id = u.id', 'u')
    ->where(['p.status' => 'published'])
    ->build();

$posts = $db->fetchAll($q);
```

### COUNT

```php
$q = Builder::table('users')
    ->count()
    ->where(['status' => 'active'])
    ->build();

$count = $db->fetchColumn($q);
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
