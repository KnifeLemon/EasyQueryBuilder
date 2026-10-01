<?php
namespace KnifeLemon\EasyQuery\Tests;

use KnifeLemon\EasyQuery\Builder;
use KnifeLemon\EasyQuery\QueryLogger;
use PHPUnit\Framework\TestCase;

class QueryLoggerTest extends TestCase
{
    protected function setUp(): void
    {
        QueryLogger::reset();
        QueryLogger::init();
    }

    protected function tearDown(): void
    {
        QueryLogger::reset();
    }

    /**
     * Test isEnabled() reflects whether Tracy is available
     */
    public function testIsEnabledReflectsTracyAvailability(): void
    {
        $this->assertSame(class_exists('Tracy\Debugger'), QueryLogger::isEnabled());
    }

    /**
     * Test getMetrics() returns the default metric keys after a reset
     */
    public function testGetMetricsShapeAfterReset(): void
    {
        $this->assertSame([
            'total_queries' => 0,
            'select_queries' => 0,
            'insert_queries' => 0,
            'update_queries' => 0,
            'delete_queries' => 0,
            'count_queries' => 0,
        ], QueryLogger::getMetrics());
    }

    /**
     * Test logging a query records it and updates the metrics
     */
    public function testLogRecordsQueryAndMetrics(): void
    {
        Builder::table('users')->where(['id' => 1])->build();

        $queries = QueryLogger::getQueries();
        $this->assertCount(1, $queries);
        $this->assertSame('select', $queries[0]['action']);
        $this->assertSame('SELECT * FROM users WHERE id = ?', $queries[0]['output']['sql']);
        $this->assertSame([1], $queries[0]['output']['params']);
        $this->assertSame('users', $queries[0]['input']['table']);
        $this->assertIsArray($queries[0]['input']['where']);
        $this->assertSame(['id = ?'], $queries[0]['input']['where']);

        $metrics = QueryLogger::getMetrics();
        $this->assertSame(1, $metrics['total_queries']);
        $this->assertSame(1, $metrics['select_queries']);
    }

    /**
     * Test logging a new action creates its metric key
     */
    public function testLogCreatesMetricKeyForNewAction(): void
    {
        Builder::table('users')->insertBatch([['name' => 'Alice']])->build();

        $metrics = QueryLogger::getMetrics();
        $this->assertSame(1, $metrics['total_queries']);
        $this->assertSame(1, $metrics['insertBatch_queries']);
    }

    /**
     * Test reset() clears queries and metrics
     */
    public function testResetClearsQueriesAndMetrics(): void
    {
        Builder::table('users')->build();

        QueryLogger::reset();

        $this->assertSame([], QueryLogger::getQueries());
        $this->assertSame(0, QueryLogger::getMetrics()['total_queries']);
    }
}
