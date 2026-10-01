<?php
namespace KnifeLemon\EasyQuery;

/**
 * QueryLogger - Logging utility for Builder with Tracy integration
 * 
 * Collects query building information and provides Tracy panel integration
 */
class QueryLogger {
    private static bool $enabled = false;
    /** @var array<int, array{id: int, action: string, input: array{table: string, alias: string, select: string, where: array<int, string>, joins: array<int, string>, groupBy: string, orderBy: string, limit: int, offset: int, setData: array<string, mixed>}, output: array{sql: string, params: array<mixed>}, timestamp: float}> */
    private static array $queries = [];
    private static float $startTime = 0;
    /** @var array<string, int> */
    private static array $metrics = [
        'total_queries' => 0,
        'select_queries' => 0,
        'insert_queries' => 0,
        'update_queries' => 0,
        'delete_queries' => 0,
        'count_queries' => 0,
    ];

    /**
     * Initialize logger and check if Tracy is available
     */
    public static function init() : void {
        if (class_exists('Tracy\Debugger')) {
            self::$enabled = true;
            self::$startTime = microtime(true);
        }
    }

    /**
     * Log a query build
     *
     * @param array{table: string, alias: string, select: string, where: array<int, string>, joins: array<int, string>, groupBy: string, orderBy: string, limit: int, offset: int, setData: array<string, mixed>} $input
     * @param array{sql: string, params: array<mixed>} $output
     */
    public static function log(string $action, array $input, array $output) : void {
        if (!self::$enabled) return;

        self::$metrics['total_queries']++;
        self::$metrics[$action . '_queries'] = (self::$metrics[$action . '_queries'] ?? 0) + 1;

        $duration = microtime(true) - self::$startTime;

        self::$queries[] = [
            'id' => count(self::$queries) + 1,
            'action' => $action,
            'input' => $input,
            'output' => $output,
            'timestamp' => $duration,
        ];
    }

    /**
     * Get all logged queries
     *
     * @return array<int, array{id: int, action: string, input: array{table: string, alias: string, select: string, where: array<int, string>, joins: array<int, string>, groupBy: string, orderBy: string, limit: int, offset: int, setData: array<string, mixed>}, output: array{sql: string, params: array<mixed>}, timestamp: float}>
     */
    public static function getQueries() : array {
        return self::$queries;
    }

    /**
     * Get metrics
     *
     * @return array<string, int>
     */
    public static function getMetrics() : array {
        return self::$metrics;
    }

    /**
     * Check if logging is enabled
     */
    public static function isEnabled() : bool {
        return self::$enabled;
    }

    /**
     * Add Tracy Bar Panel
     */
    public static function addTracyPanel() : void {
        if (!self::$enabled) return;
        
        if (class_exists('Tracy\Debugger')) {
            \Tracy\Debugger::getBar()->addPanel(new QueryPanel());
        }
    }

    /**
     * Reset logger state (for testing)
     */
    public static function reset() : void {
        self::$queries = [];
        self::$metrics = [
            'total_queries' => 0,
            'select_queries' => 0,
            'insert_queries' => 0,
            'update_queries' => 0,
            'delete_queries' => 0,
            'count_queries' => 0,
        ];
        self::$startTime = microtime(true);
    }
}
