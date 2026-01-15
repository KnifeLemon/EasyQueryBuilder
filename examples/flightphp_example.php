<?php
/**
 * FlightPHP Integration Example
 * 
 * This example demonstrates how to use GenerateQuery with the FlightPHP framework
 * 
 * Installation:
 * composer require flightphp/core
 * composer require knifelemon/generate-query
 */

require 'vendor/autoload.php';

use KnifeLemon\GenerateQuery\GenerateQuery as GQuery;

// Configure database connection using FlightPHP's SimplePdo
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

/**
 * GET /users - List all active users
 */
Flight::route('GET /users', function() {
    $q = GQuery::table('users')
        ->select(['id', 'name', 'email', 'created_at'])
        ->where(['status' => 'active'])
        ->orderBy('created_at DESC')
        ->limit(50)
        ->build();
    
    // FlightPHP's SimplePdo returns Collection objects
    $users = Flight::db()->fetchAll($q['sql'], $q['params']);
    
    // Convert Collections to arrays for JSON response
    $usersArray = array_map(fn($user) => $user->getData(), $users);
    
    Flight::json([
        'success' => true,
        'data' => $usersArray,
        'count' => count($users)
    ]);
});

/**
 * GET /users/@id - Get single user by ID
 */
Flight::route('GET /users/@id', function($id) {
    $q = GQuery::table('users')
        ->select(['id', 'name', 'email', 'status', 'created_at'])
        ->where(['id' => $id])
        ->build();
    
    // fetchRow returns a Collection object or empty Collection
    $user = Flight::db()->fetchRow($q['sql'], $q['params']);
    
    if ($user->count() === 0) {
        Flight::json(['success' => false, 'message' => 'User not found'], 404);
        return;
    }
    
    Flight::json(['success' => true, 'data' => $user->getData()]);
});

/**
 * POST /users - Create new user
 */
Flight::route('POST /users', function() {
    $data = Flight::request()->data;
    
    // Validate input
    if (empty($data->name) || empty($data->email)) {
        Flight::json(['success' => false, 'message' => 'Name and email are required'], 400);
        return;
    }
    
    $q = GQuery::table('users')
        ->insert([
            'name' => $data->name,
            'email' => $data->email,
            'status' => $data->status ?? 'active',
            'created_at' => GQuery::raw('NOW()')
        ])
        ->build();
    
    try {
        Flight::db()->runQuery($q['sql'], $q['params']);
        $userId = Flight::db()->lastInsertId();
        
        Flight::json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => ['id' => $userId]
        ], 201);
    } catch (PDOException $e) {
        Flight::json([
            'success' => false,
            'message' => 'Database error',
            'error' => $e->getMessage()
        ], 500);
    }
});

/**
 * PUT /users/@id - Update user
 */
Flight::route('PUT /users/@id', function($id) {
    $data = Flight::request()->data;
    
    $updateData = [];
    if (isset($data->name)) $updateData['name'] = $data->name;
    if (isset($data->email)) $updateData['email'] = $data->email;
    if (isset($data->status)) $updateData['status'] = $data->status;
    
    if (empty($updateData)) {
        Flight::json(['success' => false, 'message' => 'No data to update'], 400);
        return;
    }
    
    $updateData['updated_at'] = GQuery::raw('NOW()');
    
    $q = GQuery::table('users')
        ->update($updateData)
        ->where(['id' => $id])
        ->build();
    
    try {
        $stmt = Flight::db()->runQuery($q['sql'], $q['params']);
        $affected = $stmt->rowCount();
        
        if ($affected === 0) {
            Flight::json(['success' => false, 'message' => 'User not found'], 404);
            return;
        }
        
        Flight::json([
            'success' => true,
            'message' => 'User updated successfully'
        ]);
    } catch (PDOException $e) {
        Flight::json([
            'success' => false,
            'message' => 'Database error',
            'error' => $e->getMessage()
        ], 500);
    }
});

/**
 * DELETE /users/@id - Delete user
 */
Flight::route('DELETE /users/@id', function($id) {
    $q = GQuery::table('users')
        ->delete()
        ->where(['id' => $id])
        ->build();
    
    try {
        $stmt = Flight::db()->runQuery($q['sql'], $q['params']);
        $affected = $stmt->rowCount();
        
        if ($affected === 0) {
            Flight::json(['success' => false, 'message' => 'User not found'], 404);
            return;
        }
        
        Flight::json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    } catch (PDOException $e) {
        Flight::json([
            'success' => false,
            'message' => 'Database error',
            'error' => $e->getMessage()
        ], 500);
    }
});

/**
 * GET /users/search?q=keyword - Search users
 */
Flight::route('GET /users/search', function() {
    $searchTerm = Flight::request()->query['q'] ?? '';
    
    if (empty($searchTerm)) {
        Flight::json(['success' => false, 'message' => 'Search term required'], 400);
        return;
    }
    
    $q = GQuery::table('users')
        ->select(['id', 'name', 'email'])
        ->where([
            'status' => 'active',
            'name' => ['LIKE', "%{$searchTerm}%"]
        ])
        ->orderBy('name ASC')
        ->limit(20)
        ->build();
    
    $users = Flight::db()->fetchAll($q['sql'], $q['params']);
    $usersArray = array_map(fn($user) => $user->getData(), $users);
    
    Flight::json([
        'success' => true,
        'data' => $usersArray,
        'count' => count($users)
    ]);
});

/**
 * GET /posts/with-authors - Get posts with user information (JOIN example)
 */
Flight::route('GET /posts/with-authors', function() {
    $q = GQuery::table('posts')
        ->alias('p')
        ->select([
            'p.id',
            'p.title',
            'p.content',
            'p.published_at',
            'u.name AS author_name',
            'u.email AS author_email'
        ])
        ->innerJoin('users', 'p.user_id = u.id', 'u')
        ->where([
            'p.status' => 'published',
            'u.status' => 'active'
        ])
        ->orderBy('p.published_at DESC')
        ->limit(20)
        ->build();
    
    $posts = Flight::db()->fetchAll($q['sql'], $q['params']);
    $postsArray = array_map(fn($post) => $post->getData(), $posts);
    
    Flight::json([
        'success' => true,
        'data' => $postsArray,
        'count' => count($posts)
    ]);
});

/**
 * GET /stats/users - Get user statistics
 */
Flight::route('GET /stats/users', function() {
    // Count active users using fetchField (returns single value)
    $q = GQuery::table('users')
        ->count()
        ->where(['status' => 'active'])
        ->build();
    
    $activeCount = Flight::db()->fetchField($q['sql'], $q['params']);
    
    // Count total users
    $q2 = GQuery::table('users')
        ->count()
        ->build();
    
    $totalCount = Flight::db()->fetchField($q2['sql'], $q2['params']);
    
    Flight::json([
        'success' => true,
        'data' => [
            'total_users' => (int)$totalCount,
            'active_users' => (int)$activeCount,
            'inactive_users' => (int)($totalCount - $activeCount)
        ]
    ]);
});

/**
 * POST /users/batch - Batch user creation with transaction
 */
Flight::route('POST /users/batch', function() {
    $users = Flight::request()->data->users ?? [];
    
    if (empty($users) || !is_array($users)) {
        Flight::json(['success' => false, 'message' => 'Users array required'], 400);
        return;
    }
    
    try {
        // Use FlightPHP's transaction helper
        $insertedIds = Flight::db()->transaction(function($db) use ($users) {
            $ids = [];
            
            foreach ($users as $userData) {
                $q = GQuery::table('users')
                    ->insert([
                        'name' => $userData->name ?? '',
                        'email' => $userData->email ?? '',
                        'status' => 'active',
                        'created_at' => GQuery::raw('NOW()')
                    ])
                    ->build();
                
                $db->runQuery($q['sql'], $q['params']);
                $ids[] = $db->lastInsertId();
            }
            
            return $ids;
        });
        
        Flight::json([
            'success' => true,
            'message' => 'Users created successfully',
            'data' => [
                'count' => count($insertedIds),
                'ids' => $insertedIds
            ]
        ], 201);
        
    } catch (Exception $e) {
        Flight::json([
            'success' => false,
            'message' => 'Transaction failed',
            'error' => $e->getMessage()
        ], 500);
    }
});

// Start the application
Flight::start();
