<?php
/**
 * Legacy PHP / PDO Integration Example
 * 
 * This example shows how to use GenerateQuery in legacy PHP applications
 * without any framework dependencies
 */

require_once __DIR__ . '/../vendor/autoload.php';
// Or for manual installation:
// require_once __DIR__ . '/../src/GenerateQuery.php';
// require_once __DIR__ . '/../src/GenerateQueryRaw.php';

use KnifeLemon\GenerateQuery\GenerateQuery as GQuery;

// Database configuration
$config = [
    'host' => 'localhost',
    'dbname' => 'myapp',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

// Create PDO connection
try {
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ============================================================================
// Example 1: Simple SELECT
// ============================================================================
echo "<h2>Example 1: Simple SELECT</h2>\n";

$q = GQuery::table('users')
    ->select(['id', 'name', 'email'])
    ->where(['status' => 'active'])
    ->orderBy('created_at DESC')
    ->limit(10)
    ->build();

echo "<p><strong>SQL:</strong> {$q['sql']}</p>\n";
echo "<p><strong>Params:</strong> " . json_encode($q['params']) . "</p>\n";

$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);
$users = $stmt->fetchAll();

echo "<p><strong>Results:</strong> " . count($users) . " users found</p>\n";
echo "<pre>" . print_r($users, true) . "</pre>\n";

// ============================================================================
// Example 2: SELECT with JOIN
// ============================================================================
echo "<h2>Example 2: SELECT with JOIN</h2>\n";

$q = GQuery::table('posts')
    ->alias('p')
    ->select([
        'p.id',
        'p.title',
        'p.content',
        'u.name AS author_name',
        'u.email AS author_email'
    ])
    ->innerJoin('users', 'p.user_id = u.id', 'u')
    ->where([
        'p.status' => 'published',
        'p.published_at' => ['>=', '2024-01-01']
    ])
    ->orderBy('p.published_at DESC')
    ->limit(5)
    ->build();

echo "<p><strong>SQL:</strong> {$q['sql']}</p>\n";

$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);
$posts = $stmt->fetchAll();

echo "<p><strong>Results:</strong> " . count($posts) . " posts found</p>\n";

// ============================================================================
// Example 3: INSERT
// ============================================================================
echo "<h2>Example 3: INSERT</h2>\n";

$q = GQuery::table('users')
    ->insert([
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'status' => 'active',
        'created_at' => GQuery::raw('NOW()')
    ])
    ->build();

echo "<p><strong>SQL:</strong> {$q['sql']}</p>\n";
echo "<p><strong>Params:</strong> " . json_encode($q['params']) . "</p>\n";

try {
    $stmt = $pdo->prepare($q['sql']);
    $stmt->execute($q['params']);
    $newUserId = $pdo->lastInsertId();
    echo "<p><strong>Success!</strong> New user ID: {$newUserId}</p>\n";
} catch (PDOException $e) {
    echo "<p><strong>Error:</strong> {$e->getMessage()}</p>\n";
}

// ============================================================================
// Example 4: UPDATE
// ============================================================================
echo "<h2>Example 4: UPDATE</h2>\n";

$q = GQuery::table('users')
    ->update([
        'status' => 'inactive',
        'updated_at' => GQuery::raw('NOW()')
    ])
    ->where([
        'id' => 123,
        'status' => 'active'
    ])
    ->build();

echo "<p><strong>SQL:</strong> {$q['sql']}</p>\n";
echo "<p><strong>Params:</strong> " . json_encode($q['params']) . "</p>\n";

$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);
$affectedRows = $stmt->rowCount();

echo "<p><strong>Affected rows:</strong> {$affectedRows}</p>\n";

// ============================================================================
// Example 5: DELETE
// ============================================================================
echo "<h2>Example 5: DELETE</h2>\n";

$q = GQuery::table('users')
    ->delete()
    ->where([
        'id' => 999,
        'status' => 'deleted'
    ])
    ->build();

echo "<p><strong>SQL:</strong> {$q['sql']}</p>\n";

$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);
$deletedRows = $stmt->rowCount();

echo "<p><strong>Deleted rows:</strong> {$deletedRows}</p>\n";

// ============================================================================
// Example 6: COUNT
// ============================================================================
echo "<h2>Example 6: COUNT</h2>\n";

$q = GQuery::table('users')
    ->count()
    ->where(['status' => 'active'])
    ->build();

echo "<p><strong>SQL:</strong> {$q['sql']}</p>\n";

$stmt = $pdo->prepare($q['sql']);
$stmt->execute($q['params']);
$count = $stmt->fetchColumn();

echo "<p><strong>Active users count:</strong> {$count}</p>\n";

// ============================================================================
// Example 7: Complex WHERE conditions
// ============================================================================
echo "<h2>Example 7: Complex WHERE conditions</h2>\n";

$q = GQuery::table('products')
    ->alias('p')
    ->select(['p.id', 'p.name', 'p.price'])
    ->where([
        'p.category_id' => ['IN', [1, 2, 3]],
        'p.price' => ['BETWEEN', [100, 500]],
        'p.name' => ['LIKE', '%phone%'],
        'p.stock' => ['>', 0]
    ])
    ->orderBy('p.price ASC')
    ->build();

echo "<p><strong>SQL:</strong> {$q['sql']}</p>\n";
echo "<p><strong>Params:</strong> " . json_encode($q['params']) . "</p>\n";

// ============================================================================
// Example 8: Transaction with batch insert
// ============================================================================
echo "<h2>Example 8: Transaction with batch insert</h2>\n";

$usersData = [
    ['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active'],
    ['name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'active'],
    ['name' => 'Charlie', 'email' => 'charlie@example.com', 'status' => 'active']
];

$pdo->beginTransaction();

try {
    $insertedIds = [];
    
    foreach ($usersData as $userData) {
        $q = GQuery::table('users')
            ->insert(array_merge($userData, [
                'created_at' => GQuery::raw('NOW()')
            ]))
            ->build();
        
        $stmt = $pdo->prepare($q['sql']);
        $stmt->execute($q['params']);
        $insertedIds[] = $pdo->lastInsertId();
    }
    
    $pdo->commit();
    echo "<p><strong>Success!</strong> Inserted " . count($insertedIds) . " users</p>\n";
    echo "<p><strong>IDs:</strong> " . implode(', ', $insertedIds) . "</p>\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<p><strong>Transaction failed:</strong> {$e->getMessage()}</p>\n";
}

// ============================================================================
// Example 9: Dynamic query building
// ============================================================================
echo "<h2>Example 9: Dynamic query building</h2>\n";

function searchUsers($pdo, $filters) {
    $query = GQuery::table('users')->alias('u');
    
    // Start with select
    $query->select(['u.id', 'u.name', 'u.email', 'u.status']);
    
    // Build WHERE conditions dynamically
    $conditions = [];
    
    if (!empty($filters['status'])) {
        $conditions['u.status'] = $filters['status'];
    }
    
    if (!empty($filters['name'])) {
        $conditions['u.name'] = ['LIKE', "%{$filters['name']}%"];
    }
    
    if (!empty($filters['email'])) {
        $conditions['u.email'] = ['LIKE', "%{$filters['email']}%"];
    }
    
    if (!empty($filters['min_id'])) {
        $conditions['u.id'] = ['>=', $filters['min_id']];
    }
    
    if (!empty($conditions)) {
        $query->where($conditions);
    }
    
    // Add sorting and limit
    $orderBy = $filters['order_by'] ?? 'u.id DESC';
    $limit = $filters['limit'] ?? 20;
    
    $query->orderBy($orderBy)->limit($limit);
    
    $q = $query->build();
    
    $stmt = $pdo->prepare($q['sql']);
    $stmt->execute($q['params']);
    
    return $stmt->fetchAll();
}

// Test dynamic search
$filters = [
    'status' => 'active',
    'name' => 'john',
    'limit' => 10
];

$results = searchUsers($pdo, $filters);
echo "<p><strong>Search results:</strong> " . count($results) . " users found</p>\n";

// ============================================================================
// Example 10: Using raw SQL for complex expressions
// ============================================================================
echo "<h2>Example 10: Using raw SQL expressions</h2>\n";

$q = GQuery::table('users')
    ->update([
        'points' => GQuery::raw('GREATEST(0, points - 100)'),
        'last_login' => GQuery::raw('NOW()'),
        'login_count' => GQuery::raw('login_count + 1')
    ])
    ->where(['id' => 123])
    ->build();

echo "<p><strong>SQL:</strong> {$q['sql']}</p>\n";
echo "<p><strong>Params:</strong> " . json_encode($q['params']) . "</p>\n";

// ============================================================================
// Example 11: Helper function for pagination
// ============================================================================
echo "<h2>Example 11: Pagination helper</h2>\n";

function paginate($pdo, $table, $page = 1, $perPage = 10, $conditions = []) {
    // Calculate offset
    $offset = ($page - 1) * $perPage;
    
    // Get total count
    $countQuery = GQuery::table($table)->count();
    if (!empty($conditions)) {
        $countQuery->where($conditions);
    }
    $qCount = $countQuery->build();
    
    $stmt = $pdo->prepare($qCount['sql']);
    $stmt->execute($qCount['params']);
    $totalRecords = $stmt->fetchColumn();
    
    // Get paginated results
    $dataQuery = GQuery::table($table);
    if (!empty($conditions)) {
        $dataQuery->where($conditions);
    }
    $qData = $dataQuery->orderBy('id DESC')->limit($perPage, $offset)->build();
    
    $stmt = $pdo->prepare($qData['sql']);
    $stmt->execute($qData['params']);
    $records = $stmt->fetchAll();
    
    return [
        'data' => $records,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_records' => (int)$totalRecords,
            'total_pages' => ceil($totalRecords / $perPage)
        ]
    ];
}

// Test pagination
$result = paginate($pdo, 'users', 1, 5, ['status' => 'active']);
echo "<p><strong>Page 1 results:</strong> " . count($result['data']) . " records</p>\n";
echo "<p><strong>Pagination:</strong> " . json_encode($result['pagination']) . "</p>\n";

echo "\n<hr>\n<p><strong>All examples completed!</strong></p>\n";
