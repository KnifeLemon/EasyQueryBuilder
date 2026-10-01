<?php
namespace KnifeLemon\EasyQuery\Tests;

use KnifeLemon\EasyQuery\Builder;
use PHPUnit\Framework\TestCase;

class BuilderExtensionsTest extends TestCase
{
    /**
     * Test selectSum with alias
     */
    public function testSelectSumWithAlias(): void
    {
        $q = Builder::table('orders')
            ->selectSum('total', 'total_sum')
            ->build();

        $this->assertEquals('SELECT SUM(total) AS total_sum FROM orders', $q['sql']);
        $this->assertEmpty($q['params']);
    }

    /**
     * Test selectSum without alias
     */
    public function testSelectSumWithoutAlias(): void
    {
        $q = Builder::table('orders')
            ->selectSum('total')
            ->build();

        $this->assertEquals('SELECT SUM(total) FROM orders', $q['sql']);
    }

    /**
     * Test selectAvg/selectMin/selectMax/selectCount
     */
    public function testSelectAggregates(): void
    {
        $q = Builder::table('orders')
            ->selectAvg('total', 'avg_total')
            ->selectMin('total', 'min_total')
            ->selectMax('total', 'max_total')
            ->selectCount('id', 'cnt')
            ->build();

        $this->assertEquals(
            'SELECT AVG(total) AS avg_total, MIN(total) AS min_total, MAX(total) AS max_total, COUNT(id) AS cnt FROM orders',
            $q['sql']
        );
    }

    /**
     * Test aggregates append to an existing select list
     */
    public function testAggregateAppendsToSelect(): void
    {
        $q = Builder::table('orders')
            ->select(['user_id'])
            ->selectCount('id', 'total_orders')
            ->build();

        $this->assertEquals('SELECT user_id, COUNT(id) AS total_orders FROM orders', $q['sql']);
    }

    /**
     * Test distinct flag
     */
    public function testDistinct(): void
    {
        $q = Builder::table('users')
            ->distinct()
            ->select(['role', 'status'])
            ->build();

        $this->assertEquals('SELECT DISTINCT role, status FROM users', $q['sql']);
        $this->assertEmpty($q['params']);
    }

    /**
     * Test selectSubquery with subquery params ordered before WHERE params
     */
    public function testSelectSubquery(): void
    {
        $sub = Builder::table('orders')
            ->selectCount('id', 'order_count')
            ->where(['status' => 'paid']);

        $q = Builder::table('users')
            ->select(['id'])
            ->selectSubquery($sub, 'order_count')
            ->where(['active' => true])
            ->build();

        $this->assertEquals(
            'SELECT id, (SELECT COUNT(id) AS order_count FROM orders WHERE status = ?) AS order_count FROM users WHERE active = ?',
            $q['sql']
        );
        $this->assertEquals(['paid', true], $q['params']);
    }

    /**
     * Test selectSubquery keeps param order when where() is called first
     */
    public function testSelectSubqueryParamOrderWithWhereFirst(): void
    {
        $sub = Builder::table('orders')
            ->selectCount('id', 'cnt')
            ->where(['status' => 'paid']);

        $q = Builder::table('users')
            ->where(['active' => true])
            ->select(['id'])
            ->selectSubquery($sub, 'cnt')
            ->build();

        $this->assertStringContainsString(
            'SELECT id, (SELECT COUNT(id) AS cnt FROM orders WHERE status = ?) AS cnt FROM users WHERE active = ?',
            $q['sql']
        );
        $this->assertEquals(['paid', true], $q['params']);
    }

    /**
     * Test fromSubquery with param ordering
     */
    public function testFromSubquery(): void
    {
        $sub = Builder::table('users')
            ->where(['active' => true]);

        $q = Builder::table('users')
            ->fromSubquery($sub, 'u')
            ->where(['u.role' => 'admin'])
            ->build();

        $this->assertEquals(
            'SELECT * FROM (SELECT * FROM users WHERE active = ?) AS u WHERE u.role = ?',
            $q['sql']
        );
        $this->assertEquals([true, 'admin'], $q['params']);
    }

    /**
     * Test fromSubquery requires an alias
     */
    public function testFromSubqueryRequiresAlias(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('users')
            ->fromSubquery(Builder::table('users'), '');
    }

    /**
     * Test whereIn with an array of values
     */
    public function testWhereIn(): void
    {
        $q = Builder::table('users')
            ->whereIn('id', [1, 2, 3])
            ->build();

        $this->assertEquals('SELECT * FROM users WHERE id IN (?, ?, ?)', $q['sql']);
        $this->assertEquals([1, 2, 3], $q['params']);
    }

    /**
     * Test orWhereIn
     */
    public function testOrWhereIn(): void
    {
        $q = Builder::table('users')
            ->where(['role' => 'admin'])
            ->orWhereIn('id', [1, 2])
            ->build();

        $this->assertEquals('SELECT * FROM users WHERE role = ? AND (id IN (?, ?))', $q['sql']);
        $this->assertEquals(['admin', 1, 2], $q['params']);
    }

    /**
     * Test whereNotIn
     */
    public function testWhereNotIn(): void
    {
        $q = Builder::table('users')
            ->whereNotIn('status', ['banned', 'deleted'])
            ->build();

        $this->assertEquals('SELECT * FROM users WHERE status NOT IN (?, ?)', $q['sql']);
        $this->assertEquals(['banned', 'deleted'], $q['params']);
    }

    /**
     * Test orWhereNotIn
     */
    public function testOrWhereNotIn(): void
    {
        $q = Builder::table('users')
            ->where(['role' => 'admin'])
            ->orWhereNotIn('status', ['banned'])
            ->build();

        $this->assertEquals('SELECT * FROM users WHERE role = ? AND (status NOT IN (?))', $q['sql']);
        $this->assertEquals(['admin', 'banned'], $q['params']);
    }

    /**
     * Test whereIn throws for an empty array
     */
    public function testWhereInThrowsForEmptyArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('users')->whereIn('id', []);
    }

    /**
     * Test whereIn with a subquery builder and param ordering
     */
    public function testWhereInWithSubquery(): void
    {
        $sub = Builder::table('orders')
            ->select(['user_id'])
            ->where(['total' => ['>=', 100]]);

        $q = Builder::table('users')
            ->whereIn('id', $sub)
            ->where(['active' => true])
            ->build();

        $this->assertEquals(
            'SELECT * FROM users WHERE id IN (SELECT user_id FROM orders WHERE total >= ?) AND active = ?',
            $q['sql']
        );
        $this->assertEquals([100, true], $q['params']);
    }

    /**
     * Test like with wildcard escaping and 'both' position (default)
     */
    public function testLikeBoth(): void
    {
        $q = Builder::table('products')
            ->like('title', '50% off')
            ->build();

        $this->assertEquals("SELECT * FROM products WHERE title LIKE ? ESCAPE '!'", $q['sql']);
        $this->assertEquals(['%50!% off%'], $q['params']);
    }

    /**
     * Test like with 'before' position
     */
    public function testLikeBefore(): void
    {
        $q = Builder::table('products')
            ->like('title', '50% off', 'before')
            ->build();

        $this->assertEquals(['%50!% off'], $q['params']);
    }

    /**
     * Test like with 'after' position
     */
    public function testLikeAfter(): void
    {
        $q = Builder::table('products')
            ->like('title', '50% off', 'after')
            ->build();

        $this->assertEquals(['50!% off%'], $q['params']);
    }

    /**
     * Test like with 'none' position
     */
    public function testLikeNone(): void
    {
        $q = Builder::table('products')
            ->like('title', '50% off', 'none')
            ->build();

        $this->assertEquals(['50!% off'], $q['params']);
    }

    /**
     * Test like escapes underscores and the escape character
     */
    public function testLikeEscapesUnderscoreAndEscapeChar(): void
    {
        $q = Builder::table('products')
            ->like('code', 'A_B')
            ->like('msg', '100!done')
            ->build();

        $this->assertEquals(['%A!_B%', '%100!!done%'], $q['params']);
    }

    /**
     * Test orLike
     */
    public function testOrLike(): void
    {
        $q = Builder::table('users')
            ->where(['role' => 'admin'])
            ->orLike('name', 'john')
            ->build();

        $this->assertEquals(
            "SELECT * FROM users WHERE role = ? AND (name LIKE ? ESCAPE '!')",
            $q['sql']
        );
        $this->assertEquals(['admin', '%john%'], $q['params']);
    }

    /**
     * Test notLike
     */
    public function testNotLike(): void
    {
        $q = Builder::table('users')
            ->notLike('name', 'banned', 'before')
            ->build();

        $this->assertEquals("SELECT * FROM users WHERE name NOT LIKE ? ESCAPE '!'", $q['sql']);
        $this->assertEquals(['%banned'], $q['params']);
    }

    /**
     * Test orNotLike
     */
    public function testOrNotLike(): void
    {
        $q = Builder::table('users')
            ->where(['role' => 'admin'])
            ->orNotLike('name', 'temp', 'after')
            ->build();

        $this->assertEquals(
            "SELECT * FROM users WHERE role = ? AND (name NOT LIKE ? ESCAPE '!')",
            $q['sql']
        );
        $this->assertEquals(['admin', 'temp%'], $q['params']);
    }

    /**
     * Test like throws for an invalid position
     */
    public function testLikeThrowsForInvalidPosition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('users')->like('name', 'john', 'sideways');
    }

    /**
     * Test existing ['LIKE', '%value%'] array form still works
     */
    public function testExistingLikeArrayFormUnaffected(): void
    {
        $q = Builder::table('users')
            ->where(['name' => ['LIKE', '%cake%']])
            ->build();

        $this->assertEquals('SELECT * FROM users WHERE name LIKE ?', $q['sql']);
        $this->assertEquals(['%cake%'], $q['params']);
    }

    /**
     * Test nested groups building (a OR (b AND c))
     */
    public function testNestedGroups(): void
    {
        $q = Builder::table('users')
            ->groupStart()
            ->where(['role' => 'admin'])
            ->orGroupStart()
            ->where(['role' => 'moderator'])
            ->where(['status' => 'active'])
            ->groupEnd()
            ->groupEnd()
            ->build();

        $this->assertEquals(
            'SELECT * FROM users WHERE (role = ? OR (role = ? AND status = ?))',
            $q['sql']
        );
        $this->assertEquals(['admin', 'moderator', 'active'], $q['params']);
    }

    /**
     * Test simple group with AND conditions
     */
    public function testSimpleGroup(): void
    {
        $q = Builder::table('users')
            ->where(['status' => 'active'])
            ->groupStart()
            ->where(['role' => 'admin'])
            ->where(['verified' => true])
            ->groupEnd()
            ->build();

        $this->assertEquals(
            'SELECT * FROM users WHERE status = ? AND (role = ? AND verified = ?)',
            $q['sql']
        );
        $this->assertEquals(['active', 'admin', true], $q['params']);
    }

    /**
     * Test notGroupStart
     */
    public function testNotGroupStart(): void
    {
        $q = Builder::table('users')
            ->where(['status' => 'active'])
            ->notGroupStart()
            ->where(['role' => 'banned'])
            ->groupEnd()
            ->build();

        $this->assertEquals('SELECT * FROM users WHERE status = ? AND NOT (role = ?)', $q['sql']);
        $this->assertEquals(['active', 'banned'], $q['params']);
    }

    /**
     * Test notGroupStart as the leading clause still emits NOT
     */
    public function testLeadingNotGroupStart(): void
    {
        $q = Builder::table('users')
            ->notGroupStart()
            ->where(['role' => 'banned'])
            ->groupEnd()
            ->build();

        $this->assertEquals('SELECT * FROM users WHERE NOT (role = ?)', $q['sql']);
        $this->assertEquals(['banned'], $q['params']);
    }

    /**
     * Test build() throws for unbalanced groups
     */
    public function testUnbalancedGroupsThrowOnBuild(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('users')
            ->groupStart()
            ->where(['role' => 'admin'])
            ->build();
    }

    /**
     * Test groupEnd() throws without a matching groupStart()
     */
    public function testGroupEndWithoutGroupStartThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('users')->groupEnd();
    }

    /**
     * Test clearWhere() resets group state
     */
    public function testClearWhereResetsGroups(): void
    {
        $query = Builder::table('users')
            ->groupStart()
            ->where(['role' => 'admin']);

        $query->clearWhere();
        $q = $query->build();

        $this->assertEquals('SELECT * FROM users', $q['sql']);
    }

    /**
     * Test having() after GROUP BY
     */
    public function testHaving(): void
    {
        $q = Builder::table('orders')
            ->select(['user_id'])
            ->selectCount('id', 'total_orders')
            ->groupBy('user_id')
            ->having(['total_orders' => ['>', 10]])
            ->build();

        $this->assertEquals(
            'SELECT user_id, COUNT(id) AS total_orders FROM orders GROUP BY user_id HAVING total_orders > ?',
            $q['sql']
        );
        $this->assertEquals([10], $q['params']);
    }

    /**
     * Test orHaving()
     */
    public function testOrHaving(): void
    {
        $q = Builder::table('orders')
            ->select(['user_id'])
            ->selectCount('id', 'total_orders')
            ->groupBy('user_id')
            ->having(['total_orders' => ['>', 10]])
            ->orHaving(['total_orders' => ['<', 5]])
            ->build();

        $this->assertEquals(
            'SELECT user_id, COUNT(id) AS total_orders FROM orders GROUP BY user_id HAVING total_orders > ? AND (total_orders < ?)',
            $q['sql']
        );
        $this->assertEquals([10, 5], $q['params']);
    }

    /**
     * Test rightJoin
     */
    public function testRightJoin(): void
    {
        $q = Builder::table('users')
            ->alias('u')
            ->select(['u.id', 'o.total'])
            ->rightJoin('orders', 'u.id = o.user_id', 'o')
            ->build();

        $this->assertEquals(
            'SELECT u.id, o.total FROM users AS u RIGHT JOIN orders AS o ON u.id = o.user_id',
            $q['sql']
        );
    }

    /**
     * Test union
     */
    public function testUnion(): void
    {
        $q = Builder::table('users')
            ->select(['name'])
            ->where(['active' => true])
            ->union(
                Builder::table('users')
                    ->select(['name'])
                    ->where(['archived' => true])
            )
            ->build();

        $this->assertEquals(
            'SELECT name FROM users WHERE active = ? UNION SELECT name FROM users WHERE archived = ?',
            $q['sql']
        );
        $this->assertEquals([true, true], $q['params']);
    }

    /**
     * Test unionAll
     */
    public function testUnionAll(): void
    {
        $q = Builder::table('users')
            ->select(['name'])
            ->unionAll(Builder::table('users')->select(['name']))
            ->build();

        $this->assertEquals('SELECT name FROM users UNION ALL SELECT name FROM users', $q['sql']);
        $this->assertEmpty($q['params']);
    }

    /**
     * Test union strips ORDER BY and LIMIT from the member query
     */
    public function testUnionStripsMemberOrderAndLimit(): void
    {
        $q = Builder::table('users')
            ->select(['name'])
            ->union(
                Builder::table('users')
                    ->select(['name'])
                    ->where(['x' => 1])
                    ->orderBy('name DESC')
                    ->limit(3)
            )
            ->build();

        $this->assertEquals(
            'SELECT name FROM users UNION SELECT name FROM users WHERE x = ?',
            $q['sql']
        );
        $this->assertEquals([1], $q['params']);
    }

    /**
     * Test insertBatch builds a single multi-row statement
     */
    public function testInsertBatch(): void
    {
        $q = Builder::table('users')
            ->insertBatch([
                ['name' => 'Alice', 'email' => 'alice@example.com'],
                ['name' => 'Bob', 'email' => 'bob@example.com'],
            ])
            ->build();

        $this->assertEquals(
            'INSERT INTO users (name, email) VALUES (?, ?), (?, ?)',
            $q['sql']
        );
        $this->assertEquals(['Alice', 'alice@example.com', 'Bob', 'bob@example.com'], $q['params']);
    }

    /**
     * Test insertBatch throws for empty rows
     */
    public function testInsertBatchThrowsForEmptyRows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('users')->insertBatch([]);
    }

    /**
     * Test insertBatch throws for inconsistent columns
     */
    public function testInsertBatchThrowsForInconsistentColumns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('users')->insertBatch([
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Bob'],
        ])->build();
    }

    /**
     * Test insertBatch inlines raw values into the SQL instead of binding them
     */
    public function testInsertBatchWithRawValue(): void
    {
        $q = Builder::table('users')
            ->insertBatch([
                ['name' => 'Alice', 'created_at' => Builder::raw('NOW()')],
            ])
            ->build();

        $this->assertEquals(
            'INSERT INTO users (name, created_at) VALUES (?, NOW())',
            $q['sql']
        );
        $this->assertEquals(['Alice'], $q['params']);
    }

    /**
     * Test updateBatch builds a single multi-row statement
     */
    public function testUpdateBatch(): void
    {
        $q = Builder::table('users')
            ->updateBatch([
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ], 'id')
            ->build();

        $this->assertEquals(
            'UPDATE users SET name = CASE WHEN id = ? THEN ? WHEN id = ? THEN ? END WHERE id IN (?, ?)',
            $q['sql']
        );
        $this->assertEquals([1, 'Alice', 2, 'Bob', 1, 2], $q['params']);
    }

    /**
     * Test updateBatch with multiple update columns
     */
    public function testUpdateBatchMultipleColumns(): void
    {
        $q = Builder::table('users')
            ->updateBatch([
                ['id' => 1, 'name' => 'Alice', 'status' => 'active'],
                ['id' => 2, 'name' => 'Bob', 'status' => 'inactive'],
            ], 'id')
            ->build();

        $this->assertEquals(
            'UPDATE users SET name = CASE WHEN id = ? THEN ? WHEN id = ? THEN ? END, status = CASE WHEN id = ? THEN ? WHEN id = ? THEN ? END WHERE id IN (?, ?)',
            $q['sql']
        );
        $this->assertEquals([1, 'Alice', 2, 'Bob', 1, 'active', 2, 'inactive', 1, 2], $q['params']);
    }

    /**
     * Test updateBatch throws when a row is missing the WHERE column
     * 
     * Rows share the same columns so the failure comes from the WHERE column
     * check, not the "same columns" check.
     */
    public function testUpdateBatchThrowsForMissingWhereColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('users')
            ->updateBatch([
                ['name' => 'Alice'],
                ['name' => 'Bob'],
            ], 'id')
            ->build();
    }

    /**
     * Test updateBatch throws when rows use inconsistent columns
     */
    public function testUpdateBatchThrowsForInconsistentColumns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('users')
            ->updateBatch([
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2],
            ], 'id')
            ->build();
    }

    /**
     * Test updateBatch throws when the only column is the WHERE column
     */
    public function testUpdateBatchThrowsWhenOnlyWhereColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('users')
            ->updateBatch([
                ['id' => 1],
                ['id' => 2],
            ], 'id')
            ->build();
    }

    /**
     * Test updateBatch throws for empty rows
     */
    public function testUpdateBatchThrowsForEmptyRows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('users')->updateBatch([], 'id');
    }

    /**
     * Test upsertBatch builds a single statement
     */
    public function testUpsertBatch(): void
    {
        $q = Builder::table('users')
            ->upsertBatch([
                ['email' => 'a@example.com', 'points' => 1],
                ['email' => 'b@example.com', 'points' => 2],
            ], ['email'])
            ->build();

        $this->assertEquals(
            'INSERT INTO users (email, points) VALUES (?, ?), (?, ?) ON DUPLICATE KEY UPDATE points = VALUES(points)',
            $q['sql']
        );
        $this->assertEquals(['a@example.com', 1, 'b@example.com', 2], $q['params']);
    }

    /**
     * Test upsertBatch without unique keys updates all columns
     */
    public function testUpsertBatchWithoutUniqueKeys(): void
    {
        $q = Builder::table('users')
            ->upsertBatch([
                ['email' => 'a@example.com', 'points' => 1],
            ])
            ->build();

        $this->assertEquals(
            'INSERT INTO users (email, points) VALUES (?, ?) ON DUPLICATE KEY UPDATE email = VALUES(email), points = VALUES(points)',
            $q['sql']
        );
        $this->assertEquals(['a@example.com', 1], $q['params']);
    }

    /**
     * Test upsertBatch throws when every column is a unique key
     */
    public function testUpsertBatchThrowsWhenAllColumnsAreKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('users')
            ->upsertBatch([
                ['email' => 'a@example.com', 'points' => 1],
            ], ['email', 'points'])
            ->build();
    }

    /**
     * Test deleteBatch
     */
    public function testDeleteBatch(): void
    {
        $q = Builder::table('users')
            ->deleteBatch('id', [1, 2, 3])
            ->build();

        $this->assertEquals('DELETE FROM users WHERE id IN (?, ?, ?)', $q['sql']);
        $this->assertEquals([1, 2, 3], $q['params']);
    }

    /**
     * Test deleteBatch throws for empty values
     */
    public function testDeleteBatchThrowsForEmptyValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('users')->deleteBatch('id', []);
    }

    /**
     * Test when() runs the callback for a truthy condition
     */
    public function testWhenTruthy(): void
    {
        $q = Builder::table('users')
            ->when(true, function ($query) {
                $query->where(['status' => 'active']);
            })
            ->build();

        $this->assertStringContainsString('WHERE status = ?', $q['sql']);
        $this->assertEquals(['active'], $q['params']);
    }

    /**
     * Test when() skips the callback for a falsy condition
     */
    public function testWhenFalsy(): void
    {
        $q = Builder::table('users')
            ->when(false, function ($query) {
                $query->where(['status' => 'active']);
            })
            ->build();

        $this->assertEquals('SELECT * FROM users', $q['sql']);
        $this->assertEmpty($q['params']);
    }

    /**
     * Test when() keeps fluent chaining
     */
    public function testWhenChaining(): void
    {
        $q = Builder::table('users')
            ->when(false, function ($query) {
                $query->where(['status' => 'active']);
            })
            ->when(true, function ($query) {
                $query->where(['role' => 'admin']);
            })
            ->build();

        $this->assertEquals('SELECT * FROM users WHERE role = ?', $q['sql']);
        $this->assertEquals(['admin'], $q['params']);
    }

    /**
     * Test whenNot() runs the callback for a falsy condition
     */
    public function testWhenNotFalsy(): void
    {
        $q = Builder::table('users')
            ->whenNot(null, function ($query) {
                $query->where(['archived' => false]);
            })
            ->build();

        $this->assertStringContainsString('WHERE archived = ?', $q['sql']);
        $this->assertEquals([false], $q['params']);
    }

    /**
     * Test whenNot() skips the callback for a truthy condition
     */
    public function testWhenNotTruthy(): void
    {
        $q = Builder::table('users')
            ->whenNot(true, function ($query) {
                $query->where(['archived' => false]);
            })
            ->build();

        $this->assertEquals('SELECT * FROM users', $q['sql']);
    }

    /**
     * Test orderBy() second form with validated direction
     */
    public function testOrderByWithDirection(): void
    {
        $q = Builder::table('users')
            ->orderBy('id', 'DESC')
            ->build();

        $this->assertEquals('SELECT * FROM users ORDER BY id DESC', $q['sql']);
    }

    /**
     * Test orderBy() second form is case-insensitive for direction
     */
    public function testOrderByDirectionCaseInsensitive(): void
    {
        $q = Builder::table('orders')
            ->orderBy('total', 'asc')
            ->build();

        $this->assertEquals('SELECT * FROM orders ORDER BY total ASC', $q['sql']);
    }

    /**
     * Test orderBy() throws for an invalid direction
     */
    public function testOrderByThrowsForInvalidDirection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('users')->orderBy('id', 'SIDEWAYS');
    }

    /**
     * Test orderBy() second form validates the column identifier
     */
    public function testOrderByThrowsForUnsafeColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('users')->orderBy('id; DROP TABLE users--', 'DESC');
    }

    /**
     * Test multiple orderBy() calls replace (both forms)
     */
    public function testOrderByMultipleCallsReplace(): void
    {
        $q = Builder::table('users')
            ->orderBy('name ASC')
            ->orderBy('id', 'DESC')
            ->build();

        $this->assertEquals('SELECT * FROM users ORDER BY id DESC', $q['sql']);

        $q2 = Builder::table('users')
            ->orderBy('name', 'ASC')
            ->orderBy('id DESC')
            ->build();

        $this->assertEquals('SELECT * FROM users ORDER BY id DESC', $q2['sql']);
    }

    /**
     * Test build(true) resets the builder state after building
     */
    public function testBuildWithReset(): void
    {
        $query = Builder::table('users')
            ->where(['status' => 'active'])
            ->limit(5);

        $result = $query->build(true);
        $this->assertStringContainsString('WHERE status = ?', $result['sql']);

        $reset = $query->build();
        $this->assertEquals('SELECT * FROM users', $reset['sql']);
        $this->assertEmpty($reset['params']);
    }

    /**
     * Test build() without reset keeps state (existing default behavior)
     */
    public function testBuildWithoutResetKeepsState(): void
    {
        $query = Builder::table('users')
            ->where(['status' => 'active'])
            ->limit(5);

        $first = $query->build();
        $second = $query->build();

        $this->assertStringContainsString('WHERE status = ?', $first['sql']);
        $this->assertStringContainsString('WHERE status = ?', $second['sql']);
        $this->assertStringContainsString('LIMIT 5', $second['sql']);
    }

    /**
     * Test clearAll() also clears the new builder state
     */
    public function testClearAllClearsNewState(): void
    {
        $query = Builder::table('users')
            ->distinct()
            ->selectSum('points', 'total')
            ->where(['status' => 'active'])
            ->orderBy('id', 'DESC');

        $query->clearAll();

        $q = $query->build();
        $this->assertEquals('SELECT * FROM users', $q['sql']);
        $this->assertEmpty($q['params']);
    }

    /**
     * Test clearAll() preserves the action (existing behavior)
     */
    public function testClearAllPreservesAction(): void
    {
        $q = Builder::table('users')
            ->delete()
            ->where(['id' => 1])
            ->clearAll()
            ->build();

        $this->assertEquals('DELETE FROM users', $q['sql']);
        $this->assertEmpty($q['params']);
    }

    /**
     * Test buildSQL() includes HAVING and DISTINCT
     */
    public function testBuildSQLWithHavingAndDistinct(): void
    {
        $sql = Builder::table('orders')
            ->distinct()
            ->select(['user_id'])
            ->selectCount('id', 'cnt')
            ->groupBy('user_id')
            ->having(['cnt' => ['>', 5]])
            ->buildSQL();

        $this->assertStringContainsString(
            'SELECT DISTINCT user_id, COUNT(id) AS cnt FROM orders GROUP BY user_id HAVING cnt > ?',
            $sql
        );
    }

    /**
     * Test count() keeps JOIN clauses (regression)
     */
    public function testCountKeepsJoins(): void
    {
        $q = Builder::table('users')
            ->alias('u')
            ->innerJoin('posts', 'u.id = p.user_id', 'p')
            ->count()
            ->where(['u.status' => 'active'])
            ->build();

        $this->assertEquals(
            'SELECT COUNT(*) AS cnt FROM users AS u INNER JOIN posts AS p ON u.id = p.user_id WHERE u.status = ?',
            $q['sql']
        );
        $this->assertEquals(['active'], $q['params']);
    }

    /**
     * Test getParams() keeps its original behavior: it returns only the WHERE
     * parameters. HAVING, select-subquery and UNION parameters are not included;
     * the complete parameter list is available from build()['params'].
     */
    public function testGetParamsReturnsOnlyWhereParams(): void
    {
        $query = Builder::table('orders')
            ->selectCount('id', 'cnt')
            ->groupBy('user_id')
            ->having(['cnt' => ['>', 5]]);

        $this->assertEquals([], $query->getParams());
        $this->assertEquals([5], $query->build()['params']);
    }

    /**
     * Test build(true) resets the action so a write query can be reused as SELECT
     */
    public function testBuildWithResetResetsAction(): void
    {
        $query = Builder::table('users')
            ->update(['status' => 'inactive'])
            ->where(['id' => 1]);

        $first = $query->build(true);
        $this->assertStringContainsString('UPDATE users SET status = ?', $first['sql']);

        $second = $query->build();
        $this->assertEquals('SELECT * FROM users', $second['sql']);
        $this->assertEmpty($second['params']);
    }

    /**
     * Test select() accepts a plain string column list
     */
    public function testSelectAcceptsStringColumns(): void
    {
        $q = Builder::table('users')->select('id, name')->build();

        $this->assertEquals('SELECT id, name FROM users', $q['sql']);
        $this->assertEmpty($q['params']);
    }

    /**
     * Test join() without an alias derives one from the first letter of the table
     */
    public function testJoinWithoutAliasUsesFirstLetter(): void
    {
        $q = Builder::table('users')
            ->alias('u')
            ->join('posts', 'u.id = p.user_id')
            ->build();

        $this->assertEquals(
            'SELECT * FROM users AS u INNER JOIN posts AS p ON u.id = p.user_id',
            $q['sql']
        );
    }

    /**
     * Test count() with a FROM subquery keeps the subquery parameters
     */
    public function testCountWithFromSubquery(): void
    {
        $sub = Builder::table('users')->select(['id'])->where(['active' => true]);

        $q = Builder::table('users')
            ->fromSubquery($sub, 'u')
            ->count()
            ->build();

        $this->assertEquals(
            'SELECT COUNT(*) AS cnt FROM (SELECT id FROM users WHERE active = ?) AS u',
            $q['sql']
        );
        $this->assertEquals([true], $q['params']);
    }

    /**
     * Test count() does not include select-subquery parameters, since its SQL
     * never renders the SELECT list
     */
    public function testCountExcludesSelectSubqueryParams(): void
    {
        $q = Builder::table('users')
            ->selectSubquery(
                Builder::table('orders')->selectCount('id')->where(['status' => 'paid']),
                'order_count'
            )
            ->count()
            ->where(['active' => true])
            ->build();

        $this->assertEquals('SELECT COUNT(*) AS cnt FROM users WHERE active = ?', $q['sql']);
        $this->assertEquals([true], $q['params']);
        $this->assertSame(substr_count($q['sql'], '?'), count($q['params']));
    }

    /**
     * Test count() with GROUP BY and HAVING
     */
    public function testCountWithGroupByAndHaving(): void
    {
        $q = Builder::table('orders')
            ->count('id')
            ->groupBy('user_id')
            ->having(['cnt' => ['>', 5]])
            ->build();

        $this->assertEquals(
            'SELECT COUNT(id) AS cnt FROM orders GROUP BY user_id HAVING cnt > ?',
            $q['sql']
        );
        $this->assertEquals([5], $q['params']);
    }

    /**
     * Test delete() uses the base table; fromSubquery() only applies to SELECT/COUNT
     */
    public function testDeleteUsesBaseTableNotFromSubquery(): void
    {
        $q = Builder::table('users')
            ->fromSubquery(Builder::table('users')->select(['id'])->where(['active' => true]), 'u')
            ->delete()
            ->where(['role' => 'admin'])
            ->build();

        $this->assertEquals('DELETE FROM users WHERE role = ?', $q['sql']);
        $this->assertEquals(['admin'], $q['params']);
    }

    /**
     * Test build() throws when insert data is empty
     */
    public function testInsertBuildThrowsForEmptyData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('users')->insert([])->build();
    }

    /**
     * Test build() throws when update data is empty
     */
    public function testUpdateBuildThrowsForEmptyData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('users')->update([])->build();
    }

    /**
     * Test INSERT with a raw expression that has bound parameters
     */
    public function testInsertRawWithBindings(): void
    {
        $q = Builder::table('orders')
            ->insert(['total' => Builder::raw('COALESCE(subtotal, ?) + ?', [0, 10])])
            ->build();

        $this->assertEquals('INSERT INTO orders SET total = COALESCE(subtotal, ?) + ?', $q['sql']);
        $this->assertEquals([0, 10], $q['params']);
    }

    /**
     * Test ON DUPLICATE KEY UPDATE with a raw expression that has bound parameters
     */
    public function testOnDuplicateKeyUpdateRawWithBindings(): void
    {
        $q = Builder::table('user_stats')
            ->insert(['user_id' => 1, 'views' => 0])
            ->onDuplicateKeyUpdate(['views' => Builder::raw('views + ?', [5])])
            ->build();

        $this->assertEquals(
            'INSERT INTO user_stats SET user_id = ?, views = ? ON DUPLICATE KEY UPDATE views = views + ?',
            $q['sql']
        );
        $this->assertEquals([1, 0, 5], $q['params']);
    }

    /**
     * Test where() with an operator and a raw expression carrying bindings
     */
    public function testWhereRawOperatorWithBindings(): void
    {
        $q = Builder::table('products')
            ->where(['price' => ['>', Builder::raw('(SELECT AVG(price) * ? FROM products)', [0.5])]])
            ->build();

        $this->assertEquals(
            'SELECT * FROM products WHERE price > (SELECT AVG(price) * ? FROM products)',
            $q['sql']
        );
        $this->assertEquals([0.5], $q['params']);
    }

    /**
     * Test where() with a simple value equal to a raw expression carrying bindings
     */
    public function testWhereRawValueWithBindings(): void
    {
        $q = Builder::table('products')
            ->where(['stock' => Builder::raw('COALESCE(?, 0)', [7])])
            ->build();

        $this->assertEquals('SELECT * FROM products WHERE stock = COALESCE(?, 0)', $q['sql']);
        $this->assertEquals([7], $q['params']);
    }

    /**
     * Test insertBatch() with a raw expression that has bound parameters
     */
    public function testInsertBatchRawWithBindings(): void
    {
        $q = Builder::table('users')
            ->insertBatch([
                ['name' => 'Alice', 'score' => Builder::raw('COALESCE(?, 0)', [5])],
            ])
            ->build();

        $this->assertEquals(
            'INSERT INTO users (name, score) VALUES (?, COALESCE(?, 0))',
            $q['sql']
        );
        $this->assertEquals(['Alice', 5], $q['params']);
    }

    /**
     * Test upsertBatch() with a raw expression that has bound parameters
     */
    public function testUpsertBatchRawWithBindings(): void
    {
        $q = Builder::table('user_stats')
            ->upsertBatch([
                ['user_id' => 1, 'views' => Builder::raw('VALUES(views) + ?', [2])],
            ], ['user_id'])
            ->build();

        $this->assertEquals(
            'INSERT INTO user_stats (user_id, views) VALUES (?, VALUES(views) + ?) ON DUPLICATE KEY UPDATE views = VALUES(views)',
            $q['sql']
        );
        $this->assertEquals([1, 2], $q['params']);
    }

    /**
     * Test upsertBatch() throws for empty rows
     */
    public function testUpsertBatchThrowsForEmptyRows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('user_stats')->upsertBatch([]);
    }

    /**
     * Test upsertBatch() throws for inconsistent columns
     */
    public function testUpsertBatchThrowsForInconsistentColumns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Builder::table('user_stats')
            ->upsertBatch([
                ['user_id' => 1, 'views' => 1],
                ['user_id' => 2],
            ], ['user_id'])
            ->build();
    }

    /**
     * Test getSQL() (alias of buildSQL()) returns the SELECT SQL
     */
    public function testGetSqlAlias(): void
    {
        $sql = Builder::table('users')->where(['id' => 1])->getSQL();

        $this->assertEquals('SELECT * FROM users WHERE id = ?', $sql);
    }

    /**
     * Test selectSubquery() without an alias
     */
    public function testSelectSubqueryWithoutAlias(): void
    {
        $q = Builder::table('users')
            ->selectSubquery(Builder::table('orders')->selectCount('id'))
            ->build();

        $this->assertEquals('SELECT (SELECT COUNT(id) FROM orders) FROM users', $q['sql']);
        $this->assertEmpty($q['params']);
    }

    /**
     * Test nested subqueries keep their parameters in order
     */
    public function testNestedSubqueriesParamOrder(): void
    {
        $inner = Builder::table('a')->select(['id'])->where(['x' => 1]);
        $mid = Builder::table('b')->selectSubquery($inner, 'inner_id')->where(['y' => 2]);

        $q = Builder::table('c')
            ->selectSubquery($mid, 'mid_id')
            ->where(['z' => 3])
            ->build();

        $this->assertEquals(
            'SELECT (SELECT (SELECT id FROM a WHERE x = ?) AS inner_id FROM b WHERE y = ?) AS mid_id FROM c WHERE z = ?',
            $q['sql']
        );
        $this->assertEquals([1, 2, 3], $q['params']);
    }

    /**
     * Test multiple UNION parts keep their parameters in order
     */
    public function testMultipleUnionsParamOrder(): void
    {
        $q = Builder::table('a')
            ->select(['id'])
            ->where(['x' => 1])
            ->union(Builder::table('a')->select(['id'])->where(['x' => 2]))
            ->unionAll(Builder::table('a')->select(['id'])->where(['x' => 3]))
            ->build();

        $this->assertEquals(
            'SELECT id FROM a WHERE x = ? UNION SELECT id FROM a WHERE x = ? UNION ALL SELECT id FROM a WHERE x = ?',
            $q['sql']
        );
        $this->assertEquals([1, 2, 3], $q['params']);
    }

    /**
     * Test clearAll() clears subqueries, unions, HAVING and DISTINCT state
     */
    public function testClearAllClearsSubqueriesUnionsHaving(): void
    {
        $query = Builder::table('users')
            ->distinct()
            ->selectSubquery(
                Builder::table('orders')->selectCount('id')->where(['status' => 'paid']),
                'order_count'
            )
            ->fromSubquery(Builder::table('users')->select(['id'])->where(['active' => true]), 'u')
            ->where(['role' => 'admin'])
            ->groupBy('role')
            ->having(['order_count' => ['>', 2]])
            ->union(Builder::table('users')->select(['id'])->where(['archived' => true]));

        $query->clearAll();
        $q = $query->build();

        $this->assertEquals('SELECT * FROM users', $q['sql']);
        $this->assertEmpty($q['params']);
    }

    /**
     * Test build(true) resets subqueries, unions and HAVING state
     */
    public function testBuildResetClearsSubqueriesUnionsHaving(): void
    {
        $query = Builder::table('users')
            ->distinct()
            ->selectSubquery(
                Builder::table('orders')->selectCount('id')->where(['status' => 'paid']),
                'order_count'
            )
            ->where(['role' => 'admin'])
            ->union(Builder::table('users')->select(['id'])->where(['archived' => true]));

        $query->build(true);
        $q = $query->build();

        $this->assertEquals('SELECT * FROM users', $q['sql']);
        $this->assertEmpty($q['params']);
    }

    /**
     * Test like() accepts a numeric value and casts it to string
     */
    public function testLikeWithNumericValue(): void
    {
        $q = Builder::table('products')->like('code', 123)->build();

        $this->assertEquals("SELECT * FROM products WHERE code LIKE ? ESCAPE '!'", $q['sql']);
        $this->assertEquals(['%123%'], $q['params']);
    }

    /**
     * Test every generated query has exactly one bound parameter per placeholder
     */
    public function testPlaceholderCountMatchesParamCount(): void
    {
        $queries = [
            'select conditions' => Builder::table('users')
                ->where(['status' => 'active'])
                ->orWhere(['role' => 'admin', 'plan' => 'pro'])
                ->whereIn('id', [1, 2, 3])
                ->like('name', 'john')
                ->groupBy('role')
                ->having(['cnt' => ['>', 1]])
                ->orderBy('id', 'DESC')
                ->limit(5)
                ->build(),
            'select subqueries' => Builder::table('users')
                ->selectSubquery(
                    Builder::table('orders')->selectCount('id')->where(['s' => 1]),
                    'c'
                )
                ->fromSubquery(Builder::table('users')->where(['a' => 1]), 'u')
                ->where(['b' => 2])
                ->having(['c' => ['>', 3]])
                ->build(),
            'unions' => Builder::table('a')
                ->where(['x' => 1])
                ->union(Builder::table('a')->where(['x' => 2]))
                ->unionAll(Builder::table('a')->where(['x' => 3]))
                ->build(),
            'count' => Builder::table('orders')
                ->fromSubquery(Builder::table('orders')->where(['a' => 1]), 'o')
                ->count('id')
                ->where(['b' => 2])
                ->having(['cnt' => ['>', 3]])
                ->build(),
            'update' => Builder::table('users')
                ->update(['a' => 1, 'b' => Builder::raw('COALESCE(?, 0)', [9])])
                ->where(['id' => 7])
                ->build(),
            'delete' => Builder::table('users')
                ->delete()
                ->where(['id' => 7])
                ->like('name', 'x')
                ->build(),
            'insert' => Builder::table('users')
                ->insert(['a' => 1, 'b' => Builder::raw('COALESCE(?, 0)', [9])])
                ->build(),
            'insert batch' => Builder::table('users')
                ->insertBatch([['a' => Builder::raw('COALESCE(?, 0)', [1])]])
                ->build(),
            'upsert batch' => Builder::table('stats')
                ->upsertBatch([
                    ['id' => Builder::raw('?', [1]), 'v' => Builder::raw('VALUES(v) + ?', [2])],
                ], ['id'])
                ->build(),
            'update batch' => Builder::table('users')
                ->updateBatch([
                    ['id' => 1, 'name' => 'A'],
                    ['id' => 2, 'name' => 'B'],
                ], 'id')
                ->build(),
            'delete batch' => Builder::table('users')->deleteBatch('id', [1, 2])->build(),
            'nested groups' => Builder::table('users')
                ->groupStart()
                ->where(['a' => 1])
                ->orGroupStart()
                ->where(['b' => 2])
                ->whereIn('c', [3, 4])
                ->groupEnd()
                ->groupEnd()
                ->like('d', 'e')
                ->build(),
        ];

        foreach ($queries as $label => $q) {
            $this->assertSame(
                substr_count($q['sql'], '?'),
                count($q['params']),
                "Placeholder/param mismatch for: {$label} ({$q['sql']})"
            );
        }
    }

    /**
     * Test like() leaves backslashes untouched (they are literal under ESCAPE '!')
     */
    public function testLikePreservesBackslash(): void
    {
        $q = Builder::table('files')->like('path', 'a\\b', 'none')->build();

        $this->assertEquals(['a\\b'], $q['params']);
    }
}