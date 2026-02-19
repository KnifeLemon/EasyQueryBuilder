<?php
namespace KnifeLemon\EasyQuery;

/**
 * Builder - Fluent SQL Query Builder
 * 
 * A lightweight, fluent PHP SQL query builder with support for raw SQL expressions.
 * Provides automatic parameter binding for SQL injection protection.
 * 
 * @package KnifeLemon\EasyQuery
 * @author KnifeLemon
 * @license MIT
 */
class Builder {
    private string $action = 'select';
    private string $table = '';
    private string $tableAlias = '';
    private string $select = '*';
    /** @var array<string> */
    private array $joins = [];
    /** @var array<string> */
    private array $where = [];
    /** @var array<mixed> */
    private array $params = [];
    private string $groupBy = '';
    private string $orderBy = '';
    private int $limit = 0;
    private int $offset = 0;
    /** @var array<string, mixed> */
    private array $setData = [];
    private string $countColumn = '*';
    /** @var array<string, mixed> */
    private array $onDuplicateKeyUpdateData = [];

    /**
     * Constructor - Initialize Tracy logger if available
     */
    public function __construct() {
        // Initialize Tracy logger on first instantiation
        static $initialized = false;
        if (!$initialized) {
            QueryLogger::init();
            QueryLogger::addTracyPanel();
            $initialized = true;
        }
    }

    /**
     * Set the table name for the query
     * 
     * @param string $table Table name
     * @param string $alias Optional table alias
     * @return self New instance with table set
     */
    public static function table(string $table, string $alias = '') : self {
        $instance = new self();
        $instance->table = $table;
        $instance->tableAlias = $alias;
        return $instance;
    }

    /**
     * Create a raw SQL expression (inserted directly without parameter binding)
     * 
     * WARNING: Use only with trusted data or SQL functions. Never use with user input.
     * For user-provided column names, use Builder::rawSafe() or BuilderRaw::safeIdentifier().
     * 
     * @param string $value SQL expression string
     * @param array<mixed> $bindings Optional bound parameters for ? placeholders in the expression
     * @return BuilderRaw Raw SQL object
     * 
     * Example:
     * Builder::table('users')
     *     ->update([
     *         'points' => Builder::raw('GREATEST(0, points - 100)'),
     *         'updated_at' => Builder::raw('NOW()')
     *     ])
     * 
     * With bindings:
     * Builder::raw('COALESCE(amount, ?)', [0])
     */
    public static function raw(string $value, array $bindings = []) : BuilderRaw {
        return new BuilderRaw($value, $bindings);
    }

    /**
     * Create a raw SQL expression with safe identifier substitution
     * 
     * Use this when building raw SQL with user-provided column/table names.
     * Validates identifiers to prevent SQL injection.
     * 
     * @param string $expression SQL expression with {placeholder} markers for identifiers
     * @param array<string, string> $identifiers Associative array ['placeholder' => 'column_name']
     * @param array<mixed> $bindings Optional value bindings for ? placeholders
     * @return BuilderRaw Raw SQL object with validated identifiers
     * @throws \InvalidArgumentException If any identifier is invalid
     * 
     * Example:
     * // User selects which column to sum - safely validated
     * $column = $_GET['column']; // e.g., 'total_amount'
     * Builder::table('orders')
     *     ->select(Builder::rawSafe('COALESCE(SUM({col}), ?)', ['col' => $column], [0]))
     */
    public static function rawSafe(string $expression, array $identifiers, array $bindings = []) : BuilderRaw {
        return BuilderRaw::withIdentifiers($expression, $identifiers, $bindings);
    }

    /**
     * Validate and return a safe column/table identifier
     * 
     * Only allows alphanumeric characters, underscores, and dots (for table.column notation).
     * Use this when the column name comes from user input.
     * 
     * @param string $identifier The column or table name to validate
     * @return string Validated identifier
     * @throws \InvalidArgumentException If identifier contains invalid characters
     * 
     * Example:
     * $safeCol = Builder::safeIdentifier($_GET['sort_column']);
     * Builder::table('users')->orderBy($safeCol . ' DESC');
     */
    public static function safeIdentifier(string $identifier) : string {
        return BuilderRaw::safeIdentifier($identifier);
    }

    /**
     * Set an alias for the table
     * 
     * @param string $alias Table alias
     * @return self
     */
    public function alias(string $alias) : self {
        $this->tableAlias = $alias;
        return $this;
    }

    /**
     * Set the columns to select
     * 
     * @param string|array<string> $columns Column names (default: '*')
     * @return self
     */
    public function select($columns = '*') : self {
        if (is_array($columns)) {
            $this->select = implode(', ', $columns);
        } else {
            $this->select = $columns;
        }
        return $this;
    }

    /**
     * Set query action to COUNT
     * 
     * @param string $column Column to count (default: '*')
     * @return self
     */
    public function count(string $column = '*') : self {
        $this->action = 'count';
        $this->countColumn = $column;
        return $this;
    }

    /**
     * Set query action to INSERT (multiple calls merge data)
     * 
     * @param array<string, mixed> $data Associative array ['column' => 'value']
     * @return self
     */
    public function insert(array $data) : self {
        $this->action = 'insert';
        $this->setData = array_merge($this->setData, $data);
        return $this;
    }

    /**
     * Set query action to UPDATE (multiple calls merge data)
     * 
     * @param array<string, mixed> $data Associative array ['column' => 'value']
     * @return self
     */
    public function update(array $data) : self {
        $this->action = 'update';
        $this->setData = array_merge($this->setData, $data);
        return $this;
    }

    /**
     * Set query action to DELETE
     * 
     * @return self
     */
    public function delete() : self {
        $this->action = 'delete';
        return $this;
    }

    /**
     * Set ON DUPLICATE KEY UPDATE clause for INSERT queries
     * 
     * Used with insert() to update existing rows when a duplicate key error occurs.
     * Only works with MySQL/MariaDB.
     * 
     * @param array<string, mixed> $data Associative array ['column' => 'value'] to update on duplicate key
     * @return self
     * 
     * Example:
     * Builder::table('users')
     *     ->insert(['email' => 'test@example.com', 'name' => 'Test', 'points' => 100])
     *     ->onDuplicateKeyUpdate(['points' => Builder::raw('points + 100'), 'name' => 'Test Updated'])
     */
    public function onDuplicateKeyUpdate(array $data) : self {
        $this->onDuplicateKeyUpdateData = array_merge($this->onDuplicateKeyUpdateData, $data);
        return $this;
    }

    /**
     * Add a JOIN clause
     * 
     * @param string $table Table to join
     * @param string $condition Join condition
     * @param string $alias Table alias (if empty, uses first letter of table name)
     * @param string $type JOIN type (INNER, LEFT, RIGHT, FULL)
     * @return self
     */
    public function join(string $table, string $condition, string $alias = '', string $type = 'INNER') : self {
        // If no alias provided, use first letter of table name
        if (empty($alias)) {
            $alias = substr($table, 0, 1);
        }
        $this->joins[] = "{$type} JOIN {$table} AS {$alias} ON {$condition}";
        return $this;
    }

    /**
     * Add a LEFT JOIN clause
     * 
     * @param string $table Table to join
     * @param string $condition Join condition
     * @param string $alias Table alias
     * @return self
     */
    public function leftJoin(string $table, string $condition, string $alias = '') : self {
        return $this->join($table, $condition, $alias, 'LEFT');
    }

    /**
     * Add an INNER JOIN clause
     * 
     * @param string $table Table to join
     * @param string $condition Join condition
     * @param string $alias Table alias
     * @return self
     */
    public function innerJoin(string $table, string $condition, string $alias = '') : self {
        return $this->join($table, $condition, $alias, 'INNER');
    }

    /**
     * Add WHERE conditions (automatically converts to prepared statement placeholders)
     * 
     * @param array<string, mixed> $conditions Conditions in format ['column' => 'value'] or ['column' => ['operator', 'value']]
     *                          Examples: ['uid' => 123], ['name' => ['LIKE', '%test%']], ['age' => ['BETWEEN', [18, 65]]]
     * @return self
     */
    public function where(array $conditions) : self {
        $whereConditions = [];
        foreach ($conditions as $column => $value) {
            if (is_array($value) && count($value) === 2) {
                // Format: ['operator', 'value']
                [$operator, $operandValue] = $value;
                if (strtoupper($operator) === 'BETWEEN') {
                    // Special handling for BETWEEN
                    $whereConditions[] = "{$column} BETWEEN ? AND ?";
                    if (is_array($operandValue)) {
                        $this->params = array_merge($this->params, $operandValue);
                    }
                }
                else if (strtoupper($operator) === 'IN' && is_array($operandValue)) {
                    // Handle IN operator
                    $placeholders = implode(', ', array_fill(0, count($operandValue), '?'));
                    $whereConditions[] = "{$column} IN ({$placeholders})";
                    $this->params = array_merge($this->params, $operandValue);
                }
                else if (strtoupper($operator) === 'NOT IN' && is_array($operandValue)) {
                    // Handle NOT IN operator
                    $placeholders = implode(', ', array_fill(0, count($operandValue), '?'));
                    $whereConditions[] = "{$column} NOT IN ({$placeholders})";
                    $this->params = array_merge($this->params, $operandValue);
                }
                else if (strtoupper($operator) === 'IS' && $operandValue === null) {
                    // Handle IS NULL (no parameter binding)
                    $whereConditions[] = "{$column} IS NULL";
                }
                else if (strtoupper($operator) === 'IS NOT' && $operandValue === null) {
                    // Handle IS NOT NULL (no parameter binding)
                    $whereConditions[] = "{$column} IS NOT NULL";
                }
                else {
                    // General operators (=, !=, <, >, <=, >=, LIKE, etc.)
                    if ($operandValue instanceof BuilderRaw) {
                        // Raw SQL is inserted directly without binding
                        $whereConditions[] = "{$column} {$operator} {$operandValue->value}";
                        // Support for raw expressions with bindings
                        if ($operandValue->hasBindings()) {
                            $this->params = array_merge($this->params, $operandValue->getBindings());
                        }
                    } else {
                        $whereConditions[] = "{$column} {$operator} ?";
                        $this->params[] = $operandValue;
                    }
                }
            } else {
                // Simple 'value' format (default = operator)
                if ($value instanceof BuilderRaw) {
                    // Raw SQL is inserted directly without binding
                    $whereConditions[] = "{$column} = {$value->value}";
                    // Support for raw expressions with bindings
                    if ($value->hasBindings()) {
                        $this->params = array_merge($this->params, $value->getBindings());
                    }
                } else {
                    $whereConditions[] = "{$column} = ?";
                    $this->params[] = $value;
                }
            }
        }
        if (!empty($whereConditions)) {
            $this->where[] = implode(' AND ', $whereConditions);
        }
        return $this;
    }

    /**
     * Add OR grouped conditions (conditions within group are joined with OR, group is added with AND)
     * 
     * @param array<string, mixed> $conditions Conditions in format ['column' => 'value'] or ['column' => ['operator', 'value']]
     * @return self
     */
    public function orWhere(array $conditions) : self {
        $group = self::buildWhereConditions($conditions, 'OR');
        if (!empty($group['sql'])) {
            $this->where[] = '(' . $group['sql'] . ')';
            $this->params = array_merge($this->params, $group['params']);
        }
        return $this;
    }

    /**
     * Add GROUP BY clause
     * 
     * @param string $groupBy Column(s) to group by
     * @return self
     */
    public function groupBy(string $groupBy) : self {
        $this->groupBy = $groupBy;
        return $this;
    }

    /**
     * Add ORDER BY clause
     * 
     * @param string $orderBy Sort condition (e.g., 'id DESC', 'name ASC')
     * @return self
     */
    public function orderBy(string $orderBy) : self {
        $this->orderBy = $orderBy;
        return $this;
    }

    /**
     * Add LIMIT clause with optional OFFSET
     * 
     * @param int $limit Maximum number of rows to return
     * @param int $offset Number of rows to skip
     * @return self
     */
    public function limit(int $limit, int $offset = 0) : self {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    /**
     * Clear WHERE conditions (allows query builder reuse)
     * 
     * @return self
     */
    public function clearWhere() : self {
        $this->where = [];
        $this->params = [];
        return $this;
    }

    /**
     * Clear SELECT columns (reset to default)
     * 
     * @return self
     */
    public function clearSelect() : self {
        $this->select = '*';
        return $this;
    }

    /**
     * Clear JOIN clauses
     * 
     * @return self
     */
    public function clearJoin() : self {
        $this->joins = [];
        return $this;
    }

    /**
     * Clear GROUP BY clause
     * 
     * @return self
     */
    public function clearGroupBy() : self {
        $this->groupBy = '';
        return $this;
    }

    /**
     * Clear ORDER BY clause
     * 
     * @return self
     */
    public function clearOrderBy() : self {
        $this->orderBy = '';
        return $this;
    }

    /**
     * Clear LIMIT and OFFSET
     * 
     * @return self
     */
    public function clearLimit() : self {
        $this->limit = 0;
        $this->offset = 0;
        return $this;
    }

    /**
     * Clear all query conditions (reset builder to initial state)
     * 
     * @return self
     */
    public function clearAll() : self {
        $this->select = '*';
        $this->joins = [];
        $this->where = [];
        $this->params = [];
        $this->groupBy = '';
        $this->orderBy = '';
        $this->limit = 0;
        $this->offset = 0;
        $this->setData = [];
        $this->onDuplicateKeyUpdateData = [];
        return $this;
    }

    /**
     * Build and return the SQL query string
     * 
     * @return string The generated SQL query
     */
    public function buildSQL() : string {
        $tableWithAlias = !empty($this->tableAlias) ? $this->table . ' AS ' . $this->tableAlias : $this->table;
        $sql = "SELECT {$this->select} FROM {$tableWithAlias}";
        
        // Add JOINs
        if (!empty($this->joins)) {
            $sql .= " " . implode(" ", $this->joins);
        }
        
        // Add WHERE conditions
        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(" AND ", $this->where);
        }
        
        // Add GROUP BY
        if (!empty($this->groupBy)) {
            $sql .= " GROUP BY " . $this->groupBy;
        }
        
        // Add ORDER BY
        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . $this->orderBy;
        }
        
        // Add LIMIT
        if ($this->limit > 0) {
            $sql .= " LIMIT " . $this->limit;
            if ($this->offset > 0) {
                $sql .= " OFFSET " . $this->offset;
            }
        }
        
        return $sql;
    }

    /**
     * Get the parameter array for binding
     * 
     * @return array<mixed> Array of parameters to bind to prepared statement
     */
    public function getParams() : array {
        return $this->params;
    }

    /**
     * Build and return the SQL query with parameters
     * 
     * @return array{sql: string, params: array<mixed>} Associative array ['sql' => string, 'params' => array]
     * @throws \InvalidArgumentException If query data is invalid
     */
    public function build() : array {
        $result = [];
        switch ($this->action) {
            case 'select':
                $result = [
                    'sql' => $this->buildSQL(),
                    'params' => $this->params
                ];
                break;

            case 'insert':
                if (empty($this->setData)) {
                    throw new \InvalidArgumentException('Insert data is empty');
                }
                $sets = [];
                $params = [];
                foreach ($this->setData as $column => $value) {
                    if ($value instanceof BuilderRaw) {
                        // Raw SQL is inserted directly without binding
                        $sets[] = "{$column} = {$value->value}";
                        // Support for raw expressions with bindings
                        if ($value->hasBindings()) {
                            $params = array_merge($params, $value->getBindings());
                        }
                    } else {
                        $sets[] = "{$column} = ?";
                        $params[] = $value;
                    }
                }
                $sql = "INSERT INTO {$this->table} SET " . implode(', ', $sets);
                
                // Add ON DUPLICATE KEY UPDATE clause if specified
                if (!empty($this->onDuplicateKeyUpdateData)) {
                    $updateSets = [];
                    foreach ($this->onDuplicateKeyUpdateData as $column => $value) {
                        if ($value instanceof BuilderRaw) {
                            // Raw SQL is inserted directly without binding
                            $updateSets[] = "{$column} = {$value->value}";
                            // Support for raw expressions with bindings
                            if ($value->hasBindings()) {
                                $params = array_merge($params, $value->getBindings());
                            }
                        } else {
                            $updateSets[] = "{$column} = ?";
                            $params[] = $value;
                        }
                    }
                    $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updateSets);
                }
                
                $result = [
                    'sql' => $sql,
                    'params' => $params
                ];
                break;

            case 'count':
                $tableWithAlias = !empty($this->tableAlias) ? $this->table . ' AS ' . $this->tableAlias : $this->table;
                $sql = "SELECT COUNT({$this->countColumn}) AS cnt FROM {$tableWithAlias}";
                if (!empty($this->joins)) {
                    $sql .= " " . implode(" ", $this->joins);
                }
                if (!empty($this->where)) {
                    $sql .= " WHERE " . implode(" AND ", $this->where);
                }
                if (!empty($this->groupBy)) {
                    $sql .= " GROUP BY " . $this->groupBy;
                }
                $result = [
                    'sql' => $sql,
                    'params' => $this->params
                ];
                break;

            case 'update':
                if (empty($this->setData)) {
                    throw new \InvalidArgumentException('Update data is empty');
                }
                $sets = [];
                $params = [];
                foreach ($this->setData as $column => $value) {
                    if ($value instanceof BuilderRaw) {
                        // Raw SQL is inserted directly without binding
                        $sets[] = "{$column} = {$value->value}";
                        // Support for raw expressions with bindings
                        if ($value->hasBindings()) {
                            $params = array_merge($params, $value->getBindings());
                        }
                    } else {
                        $sets[] = "{$column} = ?";
                        $params[] = $value;
                    }
                }
                $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);
                if (!empty($this->where)) {
                    $sql .= " WHERE " . implode(" AND ", $this->where);
                    $params = array_merge($params, $this->params);
                }
                $result = [
                    'sql' => $sql,
                    'params' => $params
                ];
                break;

            case 'delete':
                $tableWithAlias = !empty($this->tableAlias) ? $this->table . ' AS ' . $this->tableAlias : $this->table;
                $sql = "DELETE FROM {$tableWithAlias}";
                if (!empty($this->where)) {
                    $sql .= " WHERE " . implode(" AND ", $this->where);
                }
                $result = [
                    'sql' => $sql,
                    'params' => $this->params
                ];
                break;

            default:
                throw new \InvalidArgumentException("Unsupported build action: {$this->action}");
        }

        // Log to Tracy if available
        QueryLogger::log($this->action, [
            'table' => $this->table,
            'alias' => $this->tableAlias,
            'select' => $this->select,
            'where' => $this->where,
            'joins' => $this->joins,
            'groupBy' => $this->groupBy,
            'orderBy' => $this->orderBy,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'setData' => $this->setData,
        ], $result);

        return $result;
    }

    /**
     * Alias for build() method
     * 
     * @return array{sql: string, params: array<mixed>} Associative array ['sql' => string, 'params' => array]
     */
    public function get() : array {
        return $this->build();
    }

    /**
     * Build and return only the SQL string (for use with Flight::db()->runQuery(), etc.)
     * 
     * @return string The generated SQL query string
     */
    public function getSQL() : string {
        return $this->buildSQL();
    }

    /**
     * Parse WHERE conditions from array format
     * 
     * @param array<string, mixed> $conditions Conditions in format ['column' => 'value'] or ['column' => ['operator', 'value']]
     * @param string $implodeOperator Operator to join conditions (AND or OR)
     * @return array{sql: string, params: array<mixed>} Associative array ['sql' => string, 'params' => array]
     */
    private static function buildWhereConditions(array $conditions, string $implodeOperator = 'AND') : array {
        $whereConditions = [];
        $params = [];
        foreach ($conditions as $column => $value) {
            if (is_array($value) && count($value) === 2) {
                [$operator, $operandValue] = $value;
                if (strtoupper($operator) === 'BETWEEN') {
                    $whereConditions[] = "{$column} BETWEEN ? AND ?";
                    if (is_array($operandValue)) {
                        $params = array_merge($params, $operandValue);
                    }
                } else if (strtoupper($operator) === 'IN' && is_array($operandValue)) {
                    // Handle IN operator
                    $placeholders = implode(', ', array_fill(0, count($operandValue), '?'));
                    $whereConditions[] = "{$column} IN ({$placeholders})";
                    $params = array_merge($params, $operandValue);
                } else if (strtoupper($operator) === 'NOT IN' && is_array($operandValue)) {
                    // Handle NOT IN operator
                    $placeholders = implode(', ', array_fill(0, count($operandValue), '?'));
                    $whereConditions[] = "{$column} NOT IN ({$placeholders})";
                    $params = array_merge($params, $operandValue);
                } else if (strtoupper($operator) === 'IS' && $operandValue === null) {
                    // Handle IS NULL (no parameter binding)
                    $whereConditions[] = "{$column} IS NULL";
                } else if (strtoupper($operator) === 'IS NOT' && $operandValue === null) {
                    // Handle IS NOT NULL (no parameter binding)
                    $whereConditions[] = "{$column} IS NOT NULL";
                } else {
                    if ($operandValue instanceof BuilderRaw) {
                        // Raw SQL is inserted directly without binding
                        $whereConditions[] = "{$column} {$operator} {$operandValue->value}";
                        // Support for raw expressions with bindings
                        if ($operandValue->hasBindings()) {
                            $params = array_merge($params, $operandValue->getBindings());
                        }
                    } else {
                        $whereConditions[] = "{$column} {$operator} ?";
                        $params[] = $operandValue;
                    }
                }
            } else {
                if ($value instanceof BuilderRaw) {
                    // Raw SQL is inserted directly without binding
                    $whereConditions[] = "{$column} = {$value->value}";
                    // Support for raw expressions with bindings
                    if ($value->hasBindings()) {
                        $params = array_merge($params, $value->getBindings());
                    }
                } else {
                    $whereConditions[] = "{$column} = ?";
                    $params[] = $value;
                }
            }
        }
        
        return [
            'sql' => implode(" {$implodeOperator} ", $whereConditions),
            'params' => $params
        ];
    }
}
