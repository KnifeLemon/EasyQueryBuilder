<?php
namespace KnifeLemon\EasyQuery\Tests;

use KnifeLemon\EasyQuery\Builder;
use KnifeLemon\EasyQuery\BuilderRaw;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
    /**
     * Test basic SELECT query
     */
    public function testBasicSelect(): void
    {
        $q = Builder::table('users')
            ->select(['id', 'name', 'email'])
            ->build();

        $this->assertEquals('SELECT id, name, email FROM users', $q['sql']);
        $this->assertEmpty($q['params']);
    }

    /**
     * Test SELECT with WHERE conditions
     */
    public function testSelectWithWhere(): void
    {
        $q = Builder::table('users')
            ->select(['id', 'name'])
            ->where(['status' => 'active', 'role' => 'admin'])
            ->build();

        $this->assertEquals('SELECT id, name FROM users WHERE status = ? AND role = ?', $q['sql']);
        $this->assertEquals(['active', 'admin'], $q['params']);
    }

    /**
     * Test SELECT with table alias
     */
    public function testSelectWithAlias(): void
    {
        $q = Builder::table('users')
            ->alias('u')
            ->select(['u.id', 'u.name'])
            ->where(['u.status' => 'active'])
            ->build();

        $this->assertEquals('SELECT u.id, u.name FROM users AS u WHERE u.status = ?', $q['sql']);
        $this->assertEquals(['active'], $q['params']);
    }

    /**
     * Test SELECT with INNER JOIN
     */
    public function testSelectWithInnerJoin(): void
    {
        $q = Builder::table('users')
            ->alias('u')
            ->select(['u.id', 'u.name', 'p.title'])
            ->innerJoin('posts', 'u.id = p.user_id', 'p')
            ->build();

        $this->assertEquals(
            'SELECT u.id, u.name, p.title FROM users AS u INNER JOIN posts AS p ON u.id = p.user_id',
            $q['sql']
        );
        $this->assertEmpty($q['params']);
    }

    /**
     * Test SELECT with LEFT JOIN
     */
    public function testSelectWithLeftJoin(): void
    {
        $q = Builder::table('users')
            ->alias('u')
            ->select(['u.id', 'u.name', 'p.title'])
            ->leftJoin('posts', 'u.id = p.user_id', 'p')
            ->build();

        $this->assertEquals(
            'SELECT u.id, u.name, p.title FROM users AS u LEFT JOIN posts AS p ON u.id = p.user_id',
            $q['sql']
        );
        $this->assertEmpty($q['params']);
    }

    /**
     * Test WHERE with comparison operators
     */
    public function testWhereWithOperators(): void
    {
        $q = Builder::table('products')
            ->where([
                'price' => ['>=', 100],
                'stock' => ['<', 50],
                'name' => ['LIKE', '%phone%']
            ])
            ->build();

        $this->assertEquals(
            'SELECT * FROM products WHERE price >= ? AND stock < ? AND name LIKE ?',
            $q['sql']
        );
        $this->assertEquals([100, 50, '%phone%'], $q['params']);
    }

    /**
     * Test WHERE with IN operator
     */
    public function testWhereWithIn(): void
    {
        $q = Builder::table('users')
            ->where(['id' => ['IN', [1, 2, 3, 4, 5]]])
            ->build();

        $this->assertEquals('SELECT * FROM users WHERE id IN (?, ?, ?, ?, ?)', $q['sql']);
        $this->assertEquals([1, 2, 3, 4, 5], $q['params']);
    }

    /**
     * Test WHERE with NOT IN operator
     */
    public function testWhereWithNotIn(): void
    {
        $q = Builder::table('users')
            ->where(['status' => ['NOT IN', ['banned', 'deleted', 'suspended']]])
            ->build();

        $this->assertEquals('SELECT * FROM users WHERE status NOT IN (?, ?, ?)', $q['sql']);
        $this->assertEquals(['banned', 'deleted', 'suspended'], $q['params']);
    }

    /**
     * Test WHERE with NOT IN and other conditions
     */
    public function testWhereWithNotInAndOtherConditions(): void
    {
        $q = Builder::table('users')
            ->where([
                'role' => 'user',
                'status' => ['NOT IN', ['banned', 'deleted']]
            ])
            ->build();

        $this->assertEquals('SELECT * FROM users WHERE role = ? AND status NOT IN (?, ?)', $q['sql']);
        $this->assertEquals(['user', 'banned', 'deleted'], $q['params']);
    }

    /**
     * Test WHERE with BETWEEN operator
     */
    public function testWhereWithBetween(): void
    {
        $q = Builder::table('products')
            ->where(['price' => ['BETWEEN', [100, 500]]])
            ->build();

        $this->assertEquals('SELECT * FROM products WHERE price BETWEEN ? AND ?', $q['sql']);
        $this->assertEquals([100, 500], $q['params']);
    }

    /**
     * Test orWhere condition
     */
    public function testOrWhere(): void
    {
        $q = Builder::table('users')
            ->where(['role' => 'admin'])
            ->orWhere(['role' => 'moderator'])
            ->build();

        $this->assertEquals('SELECT * FROM users WHERE role = ? AND (role = ?)', $q['sql']);
        $this->assertEquals(['admin', 'moderator'], $q['params']);
    }

    /**
     * Test orWhere with multiple conditions (should be joined with OR inside the group)
     */
    public function testOrWhereMultipleConditions(): void
    {
        $q = Builder::table('users')
            ->where(['status' => 'active'])
            ->orWhere(['role' => 'admin', 'role2' => 'moderator'])
            ->build();

        $this->assertEquals('SELECT * FROM users WHERE status = ? AND (role = ? OR role2 = ?)', $q['sql']);
        $this->assertEquals(['active', 'admin', 'moderator'], $q['params']);
    }

    /**
     * Test SELECT with ORDER BY
     */
    public function testOrderBy(): void
    {
        $q = Builder::table('users')
            ->select(['id', 'name'])
            ->orderBy('created_at DESC')
            ->build();

        $this->assertEquals('SELECT id, name FROM users ORDER BY created_at DESC', $q['sql']);
        $this->assertEmpty($q['params']);
    }

    /**
     * Test SELECT with GROUP BY
     */
    public function testGroupBy(): void
    {
        $q = Builder::table('orders')
            ->select(['user_id', 'COUNT(*) as total'])
            ->groupBy('user_id')
            ->build();

        $this->assertEquals('SELECT user_id, COUNT(*) as total FROM orders GROUP BY user_id', $q['sql']);
        $this->assertEmpty($q['params']);
    }

    /**
     * Test SELECT with LIMIT
     */
    public function testLimit(): void
    {
        $q = Builder::table('users')
            ->select(['id', 'name'])
            ->limit(10)
            ->build();

        $this->assertEquals('SELECT id, name FROM users LIMIT 10', $q['sql']);
        $this->assertEmpty($q['params']);
    }

    /**
     * Test SELECT with LIMIT and OFFSET
     */
    public function testLimitWithOffset(): void
    {
        $q = Builder::table('users')
            ->select(['id', 'name'])
            ->limit(10, 20)
            ->build();

        $this->assertEquals('SELECT id, name FROM users LIMIT 10 OFFSET 20', $q['sql']);
        $this->assertEmpty($q['params']);
    }

    /**
     * Test COUNT query
     */
    public function testCount(): void
    {
        $q = Builder::table('users')
            ->count()
            ->where(['status' => 'active'])
            ->build();

        $this->assertEquals('SELECT COUNT(*) AS cnt FROM users WHERE status = ?', $q['sql']);
        $this->assertEquals(['active'], $q['params']);
    }

    /**
     * Test COUNT with custom column
     */
    public function testCountWithColumn(): void
    {
        $q = Builder::table('orders')
            ->count('DISTINCT user_id')
            ->build();

        $this->assertEquals('SELECT COUNT(DISTINCT user_id) AS cnt FROM orders', $q['sql']);
        $this->assertEmpty($q['params']);
    }

    /**
     * Test INSERT query
     */
    public function testInsert(): void
    {
        $q = Builder::table('users')
            ->insert([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'status' => 'active'
            ])
            ->build();

        $this->assertEquals('INSERT INTO users SET name = ?, email = ?, status = ?', $q['sql']);
        $this->assertEquals(['John Doe', 'john@example.com', 'active'], $q['params']);
    }

    /**
     * Test UPDATE query
     */
    public function testUpdate(): void
    {
        $q = Builder::table('users')
            ->update([
                'status' => 'inactive',
                'updated_at' => '2026-01-15 10:00:00'
            ])
            ->where(['id' => 123])
            ->build();

        $this->assertEquals(
            'UPDATE users SET status = ?, updated_at = ? WHERE id = ?',
            $q['sql']
        );
        $this->assertEquals(['inactive', '2026-01-15 10:00:00', 123], $q['params']);
    }

    /**
     * Test DELETE query
     */
    public function testDelete(): void
    {
        $q = Builder::table('users')
            ->delete()
            ->where(['id' => 123])
            ->build();

        $this->assertEquals('DELETE FROM users WHERE id = ?', $q['sql']);
        $this->assertEquals([123], $q['params']);
    }

    /**
     * Test raw SQL in UPDATE
     */
    public function testRawSqlInUpdate(): void
    {
        $q = Builder::table('users')
            ->update([
                'points' => Builder::raw('points + 100'),
                'updated_at' => Builder::raw('NOW()')
            ])
            ->where(['id' => 123])
            ->build();

        $this->assertEquals(
            'UPDATE users SET points = points + 100, updated_at = NOW() WHERE id = ?',
            $q['sql']
        );
        $this->assertEquals([123], $q['params']);
    }

    /**
     * Test raw SQL in INSERT
     */
    public function testRawSqlInInsert(): void
    {
        $q = Builder::table('users')
            ->insert([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'created_at' => Builder::raw('NOW()')
            ])
            ->build();

        $this->assertEquals('INSERT INTO users SET name = ?, email = ?, created_at = NOW()', $q['sql']);
        $this->assertEquals(['John Doe', 'john@example.com'], $q['params']);
    }

    /**
     * Test raw SQL in WHERE condition
     */
    public function testRawSqlInWhere(): void
    {
        $q = Builder::table('products')
            ->where([
                'price' => ['>', Builder::raw('(SELECT AVG(price) FROM products)')]
            ])
            ->build();

        $this->assertEquals(
            'SELECT * FROM products WHERE price > (SELECT AVG(price) FROM products)',
            $q['sql']
        );
        $this->assertEmpty($q['params']);
    }

    /**
     * Test buildSQL method (returns only SQL string)
     */
    public function testBuildSQL(): void
    {
        $sql = Builder::table('users')
            ->select(['id', 'name'])
            ->where(['status' => 'active'])
            ->buildSQL();

        $this->assertEquals('SELECT id, name FROM users WHERE status = ?', $sql);
    }

    /**
     * Test getParams method
     */
    public function testGetParams(): void
    {
        $query = Builder::table('users')
            ->select(['id', 'name'])
            ->where(['status' => 'active', 'role' => 'admin']);

        $params = $query->getParams();

        $this->assertEquals(['active', 'admin'], $params);
    }

    /**
     * Test get method (alias for build)
     */
    public function testGetAlias(): void
    {
        $q = Builder::table('users')
            ->select(['id', 'name'])
            ->get();

        $this->assertArrayHasKey('sql', $q);
        $this->assertArrayHasKey('params', $q);
        $this->assertEquals('SELECT id, name FROM users', $q['sql']);
    }

    /**
     * Test complex query with multiple conditions
     */
    public function testComplexQuery(): void
    {
        $q = Builder::table('orders')
            ->alias('o')
            ->select(['o.id', 'o.total', 'u.name', 'p.title'])
            ->innerJoin('users', 'o.user_id = u.id', 'u')
            ->leftJoin('products', 'o.product_id = p.id', 'p')
            ->where([
                'o.status' => 'completed',
                'o.total' => ['>=', 100],
                'o.created_at' => ['>=', '2024-01-01']
            ])
            ->groupBy('o.id')
            ->orderBy('o.created_at DESC')
            ->limit(50)
            ->build();

        $this->assertStringContainsString('SELECT o.id, o.total, u.name, p.title FROM orders AS o', $q['sql']);
        $this->assertStringContainsString('INNER JOIN users AS u ON o.user_id = u.id', $q['sql']);
        $this->assertStringContainsString('LEFT JOIN products AS p ON o.product_id = p.id', $q['sql']);
        $this->assertStringContainsString('WHERE o.status = ? AND o.total >= ? AND o.created_at >= ?', $q['sql']);
        $this->assertStringContainsString('GROUP BY o.id', $q['sql']);
        $this->assertStringContainsString('ORDER BY o.created_at DESC', $q['sql']);
        $this->assertStringContainsString('LIMIT 50', $q['sql']);
        $this->assertEquals(['completed', 100, '2024-01-01'], $q['params']);
    }

    /**
     * Test empty WHERE condition
     */
    public function testEmptyWhere(): void
    {
        $q = Builder::table('users')
            ->select(['id', 'name'])
            ->where([])
            ->build();

        $this->assertEquals('SELECT id, name FROM users', $q['sql']);
        $this->assertEmpty($q['params']);
    }

    /**
     * Test multiple WHERE calls (should be combined with AND)
     */
    public function testMultipleWhereCalls(): void
    {
        $q = Builder::table('users')
            ->where(['status' => 'active'])
            ->where(['role' => 'admin'])
            ->where(['verified' => true])
            ->build();

        $this->assertEquals('SELECT * FROM users WHERE status = ? AND role = ? AND verified = ?', $q['sql']);
        $this->assertEquals(['active', 'admin', true], $q['params']);
    }

    /**
     * Test clearWhere() method
     */
    public function testClearWhere(): void
    {
        $query = Builder::table('users')
            ->select(['id', 'name'])
            ->where(['status' => 'active', 'role' => 'admin']);

        $q1 = $query->build();
        $this->assertStringContainsString('WHERE status = ? AND role = ?', $q1['sql']);
        $this->assertEquals(['active', 'admin'], $q1['params']);

        // Clear WHERE and build again
        $query->clearWhere();
        $q2 = $query->build();
        $this->assertEquals('SELECT id, name FROM users', $q2['sql']);
        $this->assertEmpty($q2['params']);
    }

    /**
     * Test clearSelect() method
     */
    public function testClearSelect(): void
    {
        $query = Builder::table('users')
            ->select(['id', 'name', 'email']);

        $q1 = $query->build();
        $this->assertStringContainsString('SELECT id, name, email', $q1['sql']);

        // Clear SELECT and build again (should default to *)
        $query->clearSelect();
        $q2 = $query->build();
        $this->assertStringContainsString('SELECT * FROM users', $q2['sql']);
    }

    /**
     * Test clearJoin() method
     */
    public function testClearJoin(): void
    {
        $query = Builder::table('users')
            ->alias('u')
            ->innerJoin('posts', 'u.id = p.user_id', 'p');

        $q1 = $query->build();
        $this->assertStringContainsString('INNER JOIN posts', $q1['sql']);

        // Clear JOINs and build again
        $query->clearJoin();
        $q2 = $query->build();
        $this->assertStringNotContainsString('INNER JOIN', $q2['sql']);
    }

    /**
     * Test clearOrderBy() method
     */
    public function testClearOrderBy(): void
    {
        $query = Builder::table('users')
            ->orderBy('created_at DESC');

        $q1 = $query->build();
        $this->assertStringContainsString('ORDER BY created_at DESC', $q1['sql']);

        // Clear ORDER BY and build again
        $query->clearOrderBy();
        $q2 = $query->build();
        $this->assertStringNotContainsString('ORDER BY', $q2['sql']);
    }

    /**
     * Test clearGroupBy() method
     */
    public function testClearGroupBy(): void
    {
        $query = Builder::table('orders')
            ->select(['user_id', 'COUNT(*) as total'])
            ->groupBy('user_id');

        $q1 = $query->build();
        $this->assertStringContainsString('GROUP BY user_id', $q1['sql']);

        // Clear GROUP BY and build again
        $query->clearGroupBy();
        $q2 = $query->build();
        $this->assertStringNotContainsString('GROUP BY', $q2['sql']);
    }

    /**
     * Test clearLimit() method
     */
    public function testClearLimit(): void
    {
        $query = Builder::table('users')
            ->limit(10, 20);

        $q1 = $query->build();
        $this->assertStringContainsString('LIMIT 10', $q1['sql']);
        $this->assertStringContainsString('OFFSET 20', $q1['sql']);

        // Clear LIMIT and build again
        $query->clearLimit();
        $q2 = $query->build();
        $this->assertStringNotContainsString('LIMIT', $q2['sql']);
        $this->assertStringNotContainsString('OFFSET', $q2['sql']);
    }

    /**
     * Test clearAll() method
     */
    public function testClearAll(): void
    {
        $query = Builder::table('users')
            ->alias('u')
            ->select(['u.id', 'u.name'])
            ->innerJoin('posts', 'u.id = p.user_id', 'p')
            ->where(['u.status' => 'active'])
            ->groupBy('u.id')
            ->orderBy('u.created_at DESC')
            ->limit(10);

        $q1 = $query->build();
        $this->assertStringContainsString('WHERE', $q1['sql']);
        $this->assertStringContainsString('JOIN', $q1['sql']);
        $this->assertStringContainsString('ORDER BY', $q1['sql']);

        // Clear everything
        $query->clearAll();
        $q2 = $query->build();
        
        // Should be basic SELECT * FROM users AS u
        $this->assertEquals('SELECT * FROM users AS u', $q2['sql']);
        $this->assertEmpty($q2['params']);
    }

    /**
     * Test query builder reuse with clearWhere()
     */
    public function testQueryBuilderReuse(): void
    {
        $baseQuery = Builder::table('users')
            ->select(['id', 'name', 'email'])
            ->where(['status' => 'active']);

        // First query
        $q1 = $baseQuery->build();
        $this->assertStringContainsString('WHERE status = ?', $q1['sql']);
        $this->assertEquals(['active'], $q1['params']);

        // Clear WHERE and add new conditions
        $baseQuery->clearWhere();
        $q2 = $baseQuery
            ->where(['role' => 'admin', 'verified' => true])
            ->build();
        
        $this->assertStringContainsString('WHERE role = ? AND verified = ?', $q2['sql']);
        $this->assertEquals(['admin', true], $q2['params']);
        $this->assertStringNotContainsString('status', $q2['sql']);
    }

    /**
     * Test raw SQL with bindings in UPDATE
     */
    public function testRawSqlWithBindingsInUpdate(): void
    {
        $q = Builder::table('orders')
            ->update([
                'total' => Builder::raw('COALESCE(subtotal, ?) + ?', [0, 10])
            ])
            ->where(['id' => 1])
            ->build();

        $this->assertEquals('UPDATE orders SET total = COALESCE(subtotal, ?) + ? WHERE id = ?', $q['sql']);
        $this->assertEquals([0, 10, 1], $q['params']);
    }

    /**
     * Test rawSafe() for user-provided column names
     */
    public function testRawSafeWithUserInput(): void
    {
        $userColumn = 'total_amount';  // Simulating user input
        
        $q = Builder::table('orders')
            ->select([
                Builder::rawSafe('COALESCE(SUM({col}), ?)', ['col' => $userColumn], [0])->value . ' AS total'
            ])
            ->build();

        $this->assertStringContainsString('COALESCE(SUM(total_amount), ?) AS total', $q['sql']);
    }

    /**
     * Test safeIdentifier() validates column names
     */
    public function testSafeIdentifier(): void
    {
        $this->assertEquals('column_name', Builder::safeIdentifier('column_name'));
        $this->assertEquals('table.column', Builder::safeIdentifier('table.column'));
    }

    /**
     * Test safeIdentifier() throws exception for invalid input
     */
    public function testSafeIdentifierThrowsExceptionForInvalidInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::safeIdentifier('column; DROP TABLE users--');
    }

    /**
     * Test orWhere with NOT IN operator
     */
    public function testOrWhereWithNotIn(): void
    {
        $q = Builder::table('users')
            ->where(['role' => 'admin'])
            ->orWhere(['status' => ['NOT IN', ['banned', 'deleted']]])
            ->build();

        $this->assertEquals('SELECT * FROM users WHERE role = ? AND (status NOT IN (?, ?))', $q['sql']);
        $this->assertEquals(['admin', 'banned', 'deleted'], $q['params']);
    }

    /**
     * Test orWhere with IN operator
     */
    public function testOrWhereWithIn(): void
    {
        $q = Builder::table('users')
            ->where(['role' => 'admin'])
            ->orWhere(['id' => ['IN', [1, 2, 3]]])
            ->build();

        $this->assertEquals('SELECT * FROM users WHERE role = ? AND (id IN (?, ?, ?))', $q['sql']);
        $this->assertEquals(['admin', 1, 2, 3], $q['params']);
    }

    /**
     * Test INSERT with ON DUPLICATE KEY UPDATE
     */
    public function testInsertWithOnDuplicateKeyUpdate(): void
    {
        $q = Builder::table('users')
            ->insert(['email' => 'test@example.com', 'name' => 'Test User', 'points' => 100])
            ->onDuplicateKeyUpdate(['points' => 200, 'name' => 'Updated User'])
            ->build();

        $this->assertEquals(
            'INSERT INTO users SET email = ?, name = ?, points = ? ON DUPLICATE KEY UPDATE points = ?, name = ?',
            $q['sql']
        );
        $this->assertEquals(['test@example.com', 'Test User', 100, 200, 'Updated User'], $q['params']);
    }

    /**
     * Test INSERT with ON DUPLICATE KEY UPDATE using raw SQL
     */
    public function testInsertWithOnDuplicateKeyUpdateRaw(): void
    {
        $q = Builder::table('users')
            ->insert(['email' => 'test@example.com', 'name' => 'Test User', 'points' => 100])
            ->onDuplicateKeyUpdate([
                'points' => Builder::raw('points + 100'),
                'updated_at' => Builder::raw('NOW()')
            ])
            ->build();

        $this->assertEquals(
            'INSERT INTO users SET email = ?, name = ?, points = ? ON DUPLICATE KEY UPDATE points = points + 100, updated_at = NOW()',
            $q['sql']
        );
        $this->assertEquals(['test@example.com', 'Test User', 100], $q['params']);
    }

    /**
     * Test INSERT with ON DUPLICATE KEY UPDATE with mixed values and raw SQL
     */
    public function testInsertWithOnDuplicateKeyUpdateMixed(): void
    {
        $q = Builder::table('users')
            ->insert(['email' => 'test@example.com', 'name' => 'Test User', 'points' => 100])
            ->onDuplicateKeyUpdate([
                'points' => Builder::raw('points + VALUES(points)'),
                'name' => 'Updated Name',
                'login_count' => Builder::raw('login_count + 1')
            ])
            ->build();

        $this->assertEquals(
            'INSERT INTO users SET email = ?, name = ?, points = ? ON DUPLICATE KEY UPDATE points = points + VALUES(points), name = ?, login_count = login_count + 1',
            $q['sql']
        );
        $this->assertEquals(['test@example.com', 'Test User', 100, 'Updated Name'], $q['params']);
    }

    /**
     * Test INSERT without ON DUPLICATE KEY UPDATE
     */
    public function testInsertWithoutOnDuplicateKeyUpdate(): void
    {
        $q = Builder::table('users')
            ->insert(['email' => 'test@example.com', 'name' => 'Test User'])
            ->build();

        $this->assertEquals(
            'INSERT INTO users SET email = ?, name = ?',
            $q['sql']
        );
        $this->assertEquals(['test@example.com', 'Test User'], $q['params']);
    }
}
