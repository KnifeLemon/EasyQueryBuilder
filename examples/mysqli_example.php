<?php
/**
 * MySQLi Integration Example
 * 
 * This example shows how to use GenerateQuery with MySQLi
 */

require_once __DIR__ . '/../vendor/autoload.php';

use KnifeLemon\GenerateQuery\GenerateQuery as GQuery;

// Database configuration
$config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'myapp'
];

// Create MySQLi connection
$mysqli = new mysqli(
    $config['host'],
    $config['username'],
    $config['password'],
    $config['database']
);

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Set charset
$mysqli->set_charset('utf8mb4');

// ============================================================================
// Example 1: Simple SELECT with MySQLi
// ============================================================================
echo "<h2>Example 1: SELECT with MySQLi</h2>\n";

$q = GQuery::table('users')
    ->select(['id', 'name', 'email', 'status'])
    ->where(['status' => 'active'])
    ->orderBy('id DESC')
    ->limit(10)
    ->build();

echo "<p><strong>SQL:</strong> {$q['sql']}</p>\n";

// Prepare statement
$stmt = $mysqli->prepare($q['sql']);

if ($stmt) {
    // Bind parameters
    if (!empty($q['params'])) {
        // Create type string (s = string, i = integer, d = double, b = blob)
        $types = str_repeat('s', count($q['params']));
        $stmt->bind_param($types, ...$q['params']);
    }
    
    // Execute
    $stmt->execute();
    
    // Get result
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    
    echo "<p><strong>Found:</strong> " . count($users) . " users</p>\n";
    echo "<pre>" . print_r($users, true) . "</pre>\n";
    
    $stmt->close();
} else {
    echo "<p><strong>Error:</strong> " . $mysqli->error . "</p>\n";
}

// ============================================================================
// Example 2: INSERT with MySQLi
// ============================================================================
echo "<h2>Example 2: INSERT with MySQLi</h2>\n";

$q = GQuery::table('users')
    ->insert([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'status' => 'active',
        'created_at' => GQuery::raw('NOW()')
    ])
    ->build();

echo "<p><strong>SQL:</strong> {$q['sql']}</p>\n";

$stmt = $mysqli->prepare($q['sql']);

if ($stmt) {
    if (!empty($q['params'])) {
        $types = str_repeat('s', count($q['params']));
        $stmt->bind_param($types, ...$q['params']);
    }
    
    if ($stmt->execute()) {
        $insertId = $mysqli->insert_id;
        echo "<p><strong>Success!</strong> New user ID: {$insertId}</p>\n";
    } else {
        echo "<p><strong>Error:</strong> " . $stmt->error . "</p>\n";
    }
    
    $stmt->close();
}

// ============================================================================
// Example 3: UPDATE with MySQLi
// ============================================================================
echo "<h2>Example 3: UPDATE with MySQLi</h2>\n";

$q = GQuery::table('users')
    ->update([
        'status' => 'inactive',
        'updated_at' => GQuery::raw('NOW()')
    ])
    ->where(['id' => 123])
    ->build();

echo "<p><strong>SQL:</strong> {$q['sql']}</p>\n";

$stmt = $mysqli->prepare($q['sql']);

if ($stmt) {
    if (!empty($q['params'])) {
        $types = str_repeat('s', count($q['params']));
        $stmt->bind_param($types, ...$q['params']);
    }
    
    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        echo "<p><strong>Affected rows:</strong> {$affectedRows}</p>\n";
    } else {
        echo "<p><strong>Error:</strong> " . $stmt->error . "</p>\n";
    }
    
    $stmt->close();
}

// ============================================================================
// Example 4: Helper function for MySQLi
// ============================================================================
echo "<h2>Example 4: Helper function for MySQLi</h2>\n";

/**
 * Execute a query with automatic parameter binding
 */
function executeQuery($mysqli, $q) {
    $stmt = $mysqli->prepare($q['sql']);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    if (!empty($q['params'])) {
        $types = '';
        foreach ($q['params'] as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        $stmt->bind_param($types, ...$q['params']);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    return $stmt;
}

/**
 * Fetch all results
 */
function fetchAll($mysqli, $q) {
    $stmt = executeQuery($mysqli, $q);
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $data;
}

/**
 * Fetch single row
 */
function fetchOne($mysqli, $q) {
    $stmt = executeQuery($mysqli, $q);
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data;
}

// Test helper functions
$q = GQuery::table('users')
    ->select(['id', 'name', 'email'])
    ->where(['status' => 'active'])
    ->limit(5)
    ->build();

try {
    $users = fetchAll($mysqli, $q);
    echo "<p><strong>Using helper function:</strong> " . count($users) . " users found</p>\n";
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> {$e->getMessage()}</p>\n";
}

// ============================================================================
// Example 5: Transaction with MySQLi
// ============================================================================
echo "<h2>Example 5: Transaction with MySQLi</h2>\n";

$mysqli->begin_transaction();

try {
    // Insert user
    $q1 = GQuery::table('users')
        ->insert([
            'name' => 'Transaction User',
            'email' => 'transaction@example.com',
            'status' => 'active'
        ])
        ->build();
    
    $stmt1 = executeQuery($mysqli, $q1);
    $userId = $mysqli->insert_id;
    $stmt1->close();
    
    // Insert related record
    $q2 = GQuery::table('user_profiles')
        ->insert([
            'user_id' => $userId,
            'bio' => 'Test bio',
            'created_at' => GQuery::raw('NOW()')
        ])
        ->build();
    
    $stmt2 = executeQuery($mysqli, $q2);
    $stmt2->close();
    
    // Commit transaction
    $mysqli->commit();
    echo "<p><strong>Transaction successful!</strong> User ID: {$userId}</p>\n";
    
} catch (Exception $e) {
    // Rollback on error
    $mysqli->rollback();
    echo "<p><strong>Transaction failed:</strong> {$e->getMessage()}</p>\n";
}

// ============================================================================
// Example 6: Complex query with JOIN
// ============================================================================
echo "<h2>Example 6: Complex query with JOIN</h2>\n";

$q = GQuery::table('orders')
    ->alias('o')
    ->select([
        'o.id',
        'o.order_number',
        'o.total',
        'u.name AS customer_name',
        'u.email AS customer_email'
    ])
    ->innerJoin('users', 'o.user_id = u.id', 'u')
    ->where([
        'o.status' => 'completed',
        'o.total' => ['>=', 100]
    ])
    ->orderBy('o.created_at DESC')
    ->limit(10)
    ->build();

echo "<p><strong>SQL:</strong> {$q['sql']}</p>\n";

try {
    $orders = fetchAll($mysqli, $q);
    echo "<p><strong>Found:</strong> " . count($orders) . " orders</p>\n";
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> {$e->getMessage()}</p>\n";
}

// Close connection
$mysqli->close();

echo "\n<hr>\n<p><strong>All MySQLi examples completed!</strong></p>\n";
