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
    /** @var array<int, array{join: string, sql: string}> */
    private array $where = [];
    /** @var array<mixed> */
    private array $params = [];
    /** @var array<mixed> */
    private array $selectParams = [];
    /** @var array<mixed> */
    private array $fromParams = [];
    /** @var array<int, array{join: string, sql: string}> */
    private array $having = [];
    /** @var array<mixed> */
    private array $havingParams = [];
    private string $groupBy = '';
    private string $orderBy = '';
    private int $limit = 0;
    private int $offset = 0;
    /** @var array<string, mixed> */
    private array $setData = [];
    private string $countColumn = '*';
    /** @var array<string, mixed> */
    private array $onDuplicateKeyUpdateData = [];
    private bool $distinct = false;
    private int $groupDepth = 0;
    private string $fromSubquery = '';
    /** @var array<int, array{builder: self, all: bool}> */
    private array $unions = [];
    /** @var array<int, array<string, mixed>> */
    private array $batchRows = [];
    private string $batchWhereColumn = '';
    /** @var array<string> */
    private array $batchUniqueKeys = [];
    /** @var array<mixed> */
    private array $batchDeleteValues = [];

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
     * Set the columns to select (replaces any previously selected columns)
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
        $this->selectParams = [];
        return $this;
    }

    /**
     * Add a SELECT DISTINCT flag to the query
     * 
     * Prepends DISTINCT to the selected columns.
     * 
     * @return self
     * 
     * Example:
     * Builder::table('users')->distinct()->select(['role'])->build();
     * // sql: SELECT DISTINCT role FROM users
     */
    public function distinct() : self {
        $this->distinct = true;
        return $this;
    }

    /**
     * Add a SUM() aggregate to the SELECT list
     * 
     * @param string $column Column to aggregate
     * @param string $alias Optional alias for the aggregate result
     * @return self
     * 
     * Example:
     * Builder::table('orders')->selectSum('total', 'total_sum')->build();
     * // sql: SELECT SUM(total) AS total_sum FROM orders
     */
    public function selectSum(string $column, string $alias = '') : self {
        return $this->appendAggregate('SUM', $column, $alias);
    }

    /**
     * Add an AVG() aggregate to the SELECT list
     * 
     * @param string $column Column to aggregate
     * @param string $alias Optional alias for the aggregate result
     * @return self
     */
    public function selectAvg(string $column, string $alias = '') : self {
        return $this->appendAggregate('AVG', $column, $alias);
    }

    /**
     * Add a MIN() aggregate to the SELECT list
     * 
     * @param string $column Column to aggregate
     * @param string $alias Optional alias for the aggregate result
     * @return self
     */
    public function selectMin(string $column, string $alias = '') : self {
        return $this->appendAggregate('MIN', $column, $alias);
    }

    /**
     * Add a MAX() aggregate to the SELECT list
     * 
     * @param string $column Column to aggregate
     * @param string $alias Optional alias for the aggregate result
     * @return self
     */
    public function selectMax(string $column, string $alias = '') : self {
        return $this->appendAggregate('MAX', $column, $alias);
    }

    /**
     * Add a COUNT() aggregate to the SELECT list
     * 
     * @param string $column Column to aggregate
     * @param string $alias Optional alias for the aggregate result
     * @return self
     */
    public function selectCount(string $column, string $alias = '') : self {
        return $this->appendAggregate('COUNT', $column, $alias);
    }

    /**
     * Add an aggregate expression to the SELECT list
     * 
     * Appends to the current list of columns. If the default '*' is still set,
     * it is replaced by the first aggregate.
     * 
     * @param string $function Aggregate function name (SUM, AVG, MIN, MAX, COUNT)
     * @param string $column Column to aggregate
     * @param string $alias Optional alias
     * @return self
     */
    private function appendAggregate(string $function, string $column, string $alias) : self {
        $expr = "{$function}({$column})";
        if ($alias !== '') {
            $expr .= " AS {$alias}";
        }
        $this->appendSelectPart($expr);
        return $this;
    }

    /**
     * Add an expression to the SELECT list
     * 
     * @param string $expr SELECT expression
     * @return void
     */
    private function appendSelectPart(string $expr) : void {
        if ($this->select === '*') {
            $this->select = $expr;
            return;
        }
        $this->select .= ', ' . $expr;
    }

    /**
     * Embed a query builder result as a subquery in the SELECT list
     * 
     * @param self $query Builder instance for the subquery
     * @param string $alias Optional alias for the subquery result
     * @return self
     * 
     * Example:
     * Builder::table('users')
     *     ->select(['id', 'name'])
     *     ->selectSubquery(Builder::table('orders')->selectCount('id'), 'order_count')
     *     ->build();
     */
    public function selectSubquery(self $query, string $alias = '') : self {
        $compiled = $query->compileSelect(false);
        $aliasSql = $alias === '' ? '' : " AS {$alias}";
        $this->appendSelectPart("({$compiled['sql']}){$aliasSql}");
        $this->selectParams = array_merge($this->selectParams, $compiled['params']);
        return $this;
    }

    /**
     * Use a query builder result as the FROM source (derived table)
     * 
     * @param self $query Builder instance for the subquery
     * @param string $alias Required alias for the derived table
     * @return self
     * @throws \InvalidArgumentException If no alias is provided
     * 
     * Example:
     * Builder::table('users')->fromSubquery(Builder::table('users')->where(['active' => true]), 'u')->build();
     */
    public function fromSubquery(self $query, string $alias = '') : self {
        if ($alias === '') {
            throw new \InvalidArgumentException('fromSubquery() requires an alias for the derived table');
        }
        $compiled = $query->compileSelect(false);
        $this->fromSubquery = "({$compiled['sql']}) AS {$alias}";
        $this->fromParams = $compiled['params'];
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
     * Set query action to INSERT with multiple rows (one multi-row statement)
     * 
     * @param array<int, array<string, mixed>> $rows List of associative arrays ['column' => 'value']
     * @return self
     * @throws \InvalidArgumentException If rows are empty or use inconsistent columns
     * 
     * Example:
     * Builder::table('users')->insertBatch([
     *     ['name' => 'Alice', 'email' => 'alice@example.com'],
     *     ['name' => 'Bob', 'email' => 'bob@example.com'],
     * ])->build();
     * // sql: INSERT INTO users (name, email) VALUES (?, ?), (?, ?)
     */
    public function insertBatch(array $rows) : self {
        if (empty($rows)) {
            throw new \InvalidArgumentException('Insert data is empty');
        }
        $this->action = 'insertBatch';
        $this->batchRows = $rows;
        return $this;
    }

    /**
     * Set query action to INSERT with multiple rows with ON DUPLICATE KEY UPDATE (MySQL/MariaDB)
     * 
     * @param array<int, array<string, mixed>> $rows List of associative arrays ['column' => 'value']
     * @param array<string> $uniqueKeys Columns that identify a duplicate key
     * @return self
     * @throws \InvalidArgumentException If rows are empty or all columns are unique keys
     * 
     * Example:
     * Builder::table('users')->upsertBatch(
     *     [['email' => 'a@example.com', 'points' => 1], ['email' => 'b@example.com', 'points' => 2]],
     *     ['email']
     * )->build();
     */
    public function upsertBatch(array $rows, array $uniqueKeys = []) : self {
        if (empty($rows)) {
            throw new \InvalidArgumentException('Insert data is empty');
        }
        $this->action = 'upsertBatch';
        $this->batchRows = $rows;
        $this->batchUniqueKeys = $uniqueKeys;
        return $this;
    }

    /**
     * Set query action to UPDATE with a batch of rows (one multi-row statement)
     * 
     * Each row must contain the value for the WHERE column plus the values to update.
     * 
     * @param array<int, array<string, mixed>> $rows List of associative arrays ['whereColumn' => 'value', 'column' => 'value']
     * @param string $whereColumn Column used to match rows in WHERE IN (...)
     * @return self
     * @throws \InvalidArgumentException If rows are empty or use inconsistent columns
     * 
     * Example:
     * Builder::table('users')->updateBatch([
     *     ['id' => 1, 'name' => 'Alice'],
     *     ['id' => 2, 'name' => 'Bob'],
     * ], 'id')->build();
     */
    public function updateBatch(array $rows, string $whereColumn) : self {
        if (empty($rows)) {
            throw new \InvalidArgumentException('Update data is empty');
        }
        $this->action = 'updateBatch';
        $this->batchRows = $rows;
        $this->batchWhereColumn = $whereColumn;
        return $this;
    }

    /**
     * Set query action to DELETE with a batch of values
     * 
     * @param string $whereColumn Column to match in WHERE IN (...)
     * @param array<mixed> $values List of values to delete
     * @return self
     * @throws \InvalidArgumentException If values are empty
     * 
     * Example:
     * Builder::table('users')->deleteBatch('id', [1, 2, 3])->build();
     * // sql: DELETE FROM users WHERE id IN (?, ?, ?)
     */
    public function deleteBatch(string $whereColumn, array $values) : self {
        if (empty($values)) {
            throw new \InvalidArgumentException('Delete values are empty');
        }
        $this->action = 'deleteBatch';
        $this->batchWhereColumn = $whereColumn;
        $this->batchDeleteValues = $values;
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
     * Add a RIGHT JOIN clause
     * 
     * @param string $table Table to join
     * @param string $condition Join condition
     * @param string $alias Table alias
     * @return self
     */
    public function rightJoin(string $table, string $condition, string $alias = '') : self {
        return $this->join($table, $condition, $alias, 'RIGHT');
    }

    /**
     * Add a UNION to the SELECT query
     * 
     * @param self $query The query builder for the second part of the union
     * @return self
     * 
     * Example:
     * Builder::table('users')->select(['name'])->where(['active' => true])
     *     ->union(Builder::table('users')->select(['name'])->where(['archived' => true]))
     *     ->build();
     */
    public function union(self $query) : self {
        return $this->addUnion($query, false);
    }

    /**
     * Add a UNION ALL to the SELECT query
     * 
     * @param self $query The query builder for the second part of the union
     * @return self
     */
    public function unionAll(self $query) : self {
        return $this->addUnion($query, true);
    }

    /**
     * Add a union query to the SELECT
     * 
     * @param self $query The query builder for the second part of the union
     * @param bool $all Whether to use UNION ALL
     * @return self
     */
    private function addUnion(self $query, bool $all) : self {
        $this->unions[] = ['builder' => $query, 'all' => $all];
        return $this;
    }

    /**
     * Add WHERE conditions (automatically converts to prepared statement placeholders)
     * 
     * @param array<string, mixed> $conditions Conditions in format ['column' => 'value'] or ['column' => ['operator', 'value']]
     *                          Examples: ['uid' => 123], ['name' => ['LIKE', '%test%']], ['age' => ['BETWEEN', [18, 65]]]
     * @return self
     */
    public function where(array $conditions) : self {
        $built = self::buildWhereConditions($conditions, 'AND');
        if ($built['sql'] !== '') {
            $this->where[] = ['join' => 'AND', 'sql' => $built['sql']];
            $this->params = array_merge($this->params, $built['params']);
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
            $this->where[] = ['join' => 'AND', 'sql' => '(' . $group['sql'] . ')'];
            $this->params = array_merge($this->params, $group['params']);
        }
        return $this;
    }

    /**
     * Add a WHERE IN condition (values become prepared placeholders)
     * 
     * Accepts a list of values or a query builder for an IN subquery.
     * 
     * @param string $column Column name
     * @param array<mixed>|self $values List of values or a Builder for a subquery
     * @return self
     * @throws \InvalidArgumentException If an empty array is provided
     * 
     * Example:
     * Builder::table('users')->whereIn('id', [1, 2, 3])->build();
     * // sql: SELECT * FROM users WHERE id IN (?, ?, ?)
     */
    public function whereIn(string $column, $values) : self {
        $this->where[] = ['join' => 'AND', 'sql' => $this->buildInSql($column, 'IN', $values)];
        return $this;
    }

    /**
     * Add an OR WHERE IN condition
     * 
     * @param string $column Column name
     * @param array<mixed>|self $values List of values or a Builder for a subquery
     * @return self
     */
    public function orWhereIn(string $column, $values) : self {
        $this->where[] = ['join' => 'AND', 'sql' => '(' . $this->buildInSql($column, 'IN', $values) . ')'];
        return $this;
    }

    /**
     * Add a WHERE NOT IN condition
     * 
     * @param string $column Column name
     * @param array<mixed>|self $values List of values or a Builder for a subquery
     * @return self
     */
    public function whereNotIn(string $column, $values) : self {
        $this->where[] = ['join' => 'AND', 'sql' => $this->buildInSql($column, 'NOT IN', $values)];
        return $this;
    }

    /**
     * Add an OR WHERE NOT IN condition
     * 
     * @param string $column Column name
     * @param array<mixed>|self $values List of values or a Builder for a subquery
     * @return self
     */
    public function orWhereNotIn(string $column, $values) : self {
        $this->where[] = ['join' => 'AND', 'sql' => '(' . $this->buildInSql($column, 'NOT IN', $values) . ')'];
        return $this;
    }

    /**
     * Build the SQL and parameters for an IN/NOT IN condition
     * 
     * @param string $column Column name
     * @param string $operator 'IN' or 'NOT IN'
     * @param array<mixed>|self $values List of values or a Builder for a subquery
     * @return string The condition SQL
     * @throws \InvalidArgumentException If an empty array is provided
     */
    private function buildInSql(string $column, string $operator, $values) : string {
        if ($values instanceof self) {
            $compiled = $values->compileSelect(false);
            $this->params = array_merge($this->params, $compiled['params']);
            return "{$column} {$operator} ({$compiled['sql']})";
        }
        if (!is_array($values) || empty($values)) {
            throw new \InvalidArgumentException("{$operator} requires a non-empty list of values");
        }
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->params = array_merge($this->params, array_values($values));
        return "{$column} {$operator} ({$placeholders})";
    }

    /**
     * Add a LIKE condition (wildcards escaped, value bound as a parameter)
     * 
     * The value is escaped so user input cannot inject unescaped wildcards.
     * An explicit ESCAPE clause is emitted for compatibility with
     * databases running with NO_BACKSLASH_ESCAPES.
     * 
     * @param string $column Column name
     * @param string|int|float $value Value to match (wildcards are escaped)
     * @param string $position 'both', 'before', 'after', or 'none'
     * @return self
     * @throws \InvalidArgumentException If position is invalid
     * 
     * Example:
     * Builder::table('products')->like('title', '50% off', 'both')->build();
     * // sql: SELECT * FROM products WHERE title LIKE ? ESCAPE '!'
     * // params: ['%50!% off%']
     */
    public function like(string $column, $value, string $position = 'both') : self {
        $this->addLike($column, 'LIKE', $value, $position);
        return $this;
    }

    /**
     * Add an OR LIKE condition
     * 
     * @param string $column Column name
     * @param string|int|float $value Value to match (wildcards are escaped)
     * @param string $position 'both', 'before', 'after', or 'none'
     * @return self
     */
    public function orLike(string $column, $value, string $position = 'both') : self {
        $this->addLike($column, 'LIKE', $value, $position, true);
        return $this;
    }

    /**
     * Add a NOT LIKE condition
     * 
     * @param string $column Column name
     * @param string|int|float $value Value to match (wildcards are escaped)
     * @param string $position 'both', 'before', 'after', or 'none'
     * @return self
     */
    public function notLike(string $column, $value, string $position = 'both') : self {
        $this->addLike($column, 'NOT LIKE', $value, $position);
        return $this;
    }

    /**
     * Add an OR NOT LIKE condition
     * 
     * @param string $column Column name
     * @param string|int|float $value Value to match (wildcards are escaped)
     * @param string $position 'both', 'before', 'after', or 'none'
     * @return self
     */
    public function orNotLike(string $column, $value, string $position = 'both') : self {
        $this->addLike($column, 'NOT LIKE', $value, $position, true);
        return $this;
    }

    /**
     * Append a LIKE condition part
     * 
     * @param string $column Column name
     * @param string $operator 'LIKE' or 'NOT LIKE'
     * @param string|int|float $value Value to match
     * @param string $position 'both', 'before', 'after', or 'none'
     * @param bool $or Wrap condition in parentheses as an OR group
     * @return void
     * @throws \InvalidArgumentException If position is invalid
     */
    private function addLike(string $column, string $operator, $value, string $position, bool $or = false) : void {
        if (!in_array($position, ['both', 'before', 'after', 'none'], true)) {
            throw new \InvalidArgumentException(
                "Invalid LIKE position: '{$position}'. Expected 'both', 'before', 'after', or 'none'."
            );
        }
        $escaped = $this->escapeLikeValue($value);
        if ($position === 'both') {
            $escaped = "%{$escaped}%";
        } elseif ($position === 'before') {
            $escaped = "%{$escaped}";
        } elseif ($position === 'after') {
            $escaped = "{$escaped}%";
        }
        $this->params[] = $escaped;
        $sql = "{$column} {$operator} ? ESCAPE '!'";
        $this->where[] = ['join' => 'AND', 'sql' => $or ? '(' . $sql . ')' : $sql];
    }

    /**
     * Escape LIKE wildcards in a value
     * 
     * Escapes the escape character, '%' and '_' using '!' as the escape character.
     * 
     * @param string|int|float $value Value to escape
     * @return string Escaped value
     */
    private function escapeLikeValue($value) : string {
        $str = (string) $value;
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $str);
    }

    /**
     * Start a nested condition group
     * 
     * Groups must be balanced: every groupStart() needs a matching groupEnd().
     * 
     * @return self
     * 
     * Example:
     * Builder::table('users')
     *     ->where(['status' => 'active'])
     *     ->groupStart()
     *     ->where(['role' => 'admin'])
     *     ->where(['plan' => 'premium'])
     *     ->groupEnd()
     *     ->build();
     * // WHERE status = ? AND (role = ? AND plan = ?)
     */
    public function groupStart() : self {
        $this->where[] = ['join' => 'AND', 'sql' => '('];
        $this->groupDepth++;
        return $this;
    }

    /**
     * Start a nested condition group joined with OR
     * 
     * @return self
     * 
     * Example:
     * Builder::table('users')
     *     ->groupStart()
     *     ->where(['role' => 'admin'])
     *     ->orGroupStart()
     *     ->where(['role' => 'moderator'])
     *     ->where(['status' => 'active'])
     *     ->groupEnd()
     *     ->groupEnd()
     *     ->build();
     * // WHERE (role = ? OR (role = ? AND status = ?))
     */
    public function orGroupStart() : self {
        $this->where[] = ['join' => 'OR', 'sql' => '('];
        $this->groupDepth++;
        return $this;
    }

    /**
     * Start a nested condition group joined with AND NOT
     * 
     * @return self
     * 
     * Example:
     * Builder::table('users')
     *     ->where(['status' => 'active'])
     *     ->notGroupStart()
     *     ->where(['role' => 'banned'])
     *     ->groupEnd()
     *     ->build();
     * // WHERE status = ? AND NOT (role = ?)
     */
    public function notGroupStart() : self {
        $this->where[] = ['join' => 'AND NOT', 'sql' => '('];
        $this->groupDepth++;
        return $this;
    }

    /**
     * Close a nested condition group opened with groupStart()/orGroupStart()/notGroupStart()
     * 
     * @return self
     * @throws \InvalidArgumentException If there is no open group to close
     */
    public function groupEnd() : self {
        if ($this->groupDepth <= 0) {
            throw new \InvalidArgumentException('groupEnd() called without a matching groupStart()');
        }
        $this->where[] = ['join' => 'AND', 'sql' => ')'];
        $this->groupDepth--;
        return $this;
    }

    /**
     * Add HAVING conditions (filters applied after GROUP BY)
     * 
     * @param array<string, mixed> $conditions Conditions in the same format as where()
     * @return self
     * 
     * Example:
     * Builder::table('orders')
     *     ->select(['user_id'])
     *     ->selectCount('id', 'total_orders')
     *     ->groupBy('user_id')
     *     ->having(['total_orders' => ['>', 10]])
     *     ->build();
     */
    public function having(array $conditions) : self {
        $built = self::buildWhereConditions($conditions, 'AND');
        if ($built['sql'] !== '') {
            $this->having[] = ['join' => 'AND', 'sql' => $built['sql']];
            $this->havingParams = array_merge($this->havingParams, $built['params']);
        }
        return $this;
    }

    /**
     * Add OR HAVING conditions
     * 
     * @param array<string, mixed> $conditions Conditions in the same format as where()
     * @return self
     */
    public function orHaving(array $conditions) : self {
        $group = self::buildWhereConditions($conditions, 'OR');
        if (!empty($group['sql'])) {
            $this->having[] = ['join' => 'AND', 'sql' => '(' . $group['sql'] . ')'];
            $this->havingParams = array_merge($this->havingParams, $group['params']);
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
     * Two forms are supported:
     * - orderBy('created_at DESC') - a full sort expression
     * - orderBy('id', 'DESC') - a validated column with an ASC/DESC direction
     * 
     * Calling orderBy() multiple times replaces the previous value.
     * 
     * @param string $orderBy Sort expression or column name
     * @param string|null $direction Optional direction ('ASC' or 'DESC')
     * @return self
     * @throws \InvalidArgumentException If direction is invalid or the column is unsafe
     */
    public function orderBy(string $orderBy, ?string $direction = null) : self {
        if ($direction !== null) {
            $safeColumn = Builder::safeIdentifier($orderBy);
            $dir = strtoupper($direction);
            if (!in_array($dir, ['ASC', 'DESC'], true)) {
                throw new \InvalidArgumentException(
                    "Invalid ORDER BY direction: '{$direction}'. Only ASC and DESC are allowed."
                );
            }
            $this->orderBy = "{$safeColumn} {$dir}";
            return $this;
        }
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
     * Conditionally apply a callback to the builder
     * 
     * The callback is only invoked when the condition is truthy.
     * 
     * @param mixed $condition Condition to evaluate
     * @param callable $callback Callback receiving the builder, e.g. function (self $query) : self
     * @return self
     * 
     * Example:
     * Builder::table('users')
     *     ->when($search, function ($q) use ($search) { return $q->like('name', $search); })
     *     ->build();
     */
    public function when($condition, callable $callback) : self {
        if ($condition) {
            $callback($this);
        }
        return $this;
    }

    /**
     * Conditionally apply a callback to the builder when the condition is falsy
     * 
     * @param mixed $condition Condition to evaluate
     * @param callable $callback Callback receiving the builder, e.g. function (self $query) : self
     * @return self
     */
    public function whenNot($condition, callable $callback) : self {
        if (!$condition) {
            $callback($this);
        }
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
        $this->groupDepth = 0;
        return $this;
    }

    /**
     * Clear SELECT columns (reset to default)
     * 
     * @return self
     */
    public function clearSelect() : self {
        $this->select = '*';
        $this->selectParams = [];
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
        $this->selectParams = [];
        $this->joins = [];
        $this->where = [];
        $this->params = [];
        $this->fromParams = [];
        $this->having = [];
        $this->havingParams = [];
        $this->groupBy = '';
        $this->orderBy = '';
        $this->limit = 0;
        $this->offset = 0;
        $this->setData = [];
        $this->onDuplicateKeyUpdateData = [];
        $this->distinct = false;
        $this->groupDepth = 0;
        $this->fromSubquery = '';
        $this->unions = [];
        $this->batchRows = [];
        $this->batchWhereColumn = '';
        $this->batchUniqueKeys = [];
        $this->batchDeleteValues = [];
        return $this;
    }

    /**
     * Build and return the SQL query string (always builds a SELECT query)
     * 
     * @return string The generated SQL query
     */
    public function buildSQL() : string {
        return $this->compileSelect()['sql'];
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
     * @param bool $reset Whether to reset the builder state after building (default: false)
     * @return array{sql: string, params: array<mixed>} Associative array ['sql' => string, 'params' => array]
     * @throws \InvalidArgumentException If query data is invalid
     */
    public function build(bool $reset = false) : array {
        $result = [];
        switch ($this->action) {
            case 'select':
                $compiled = $this->compileSelect();
                $result = [
                    'sql' => $compiled['sql'],
                    'params' => $compiled['params']
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

            case 'insertBatch':
                if (empty($this->batchRows)) {
                    throw new \InvalidArgumentException('insertBatch() requires at least one row');
                }
                $result = $this->buildInsertBatch();
                break;

            case 'upsertBatch':
                if (empty($this->batchRows)) {
                    throw new \InvalidArgumentException('upsertBatch() requires at least one row');
                }
                $result = $this->buildUpsertBatch();
                break;

            case 'updateBatch':
                if (empty($this->batchRows)) {
                    throw new \InvalidArgumentException('updateBatch() requires at least one row');
                }
                $result = $this->buildUpdateBatch();
                break;

            case 'deleteBatch':
                if (empty($this->batchDeleteValues)) {
                    throw new \InvalidArgumentException('deleteBatch() requires at least one value');
                }
                $placeholders = implode(', ', array_fill(0, count($this->batchDeleteValues), '?'));
                $result = [
                    'sql' => "DELETE FROM {$this->table} WHERE {$this->batchWhereColumn} IN ({$placeholders})",
                    'params' => $this->batchDeleteValues
                ];
                break;

            case 'count':
                $this->assertBalancedGroups();
                $tableWithAlias = !empty($this->fromSubquery)
                    ? $this->fromSubquery
                    : (!empty($this->tableAlias) ? $this->table . ' AS ' . $this->tableAlias : $this->table);
                $sql = "SELECT COUNT({$this->countColumn}) AS cnt FROM {$tableWithAlias}";
                if (!empty($this->joins)) {
                    $sql .= " " . implode(" ", $this->joins);
                }
                $whereSql = $this->renderParts($this->where);
                if ($whereSql !== '') {
                    $sql .= " WHERE " . $whereSql;
                }
                if (!empty($this->groupBy)) {
                    $sql .= " GROUP BY " . $this->groupBy;
                }
                $havingSql = $this->renderParts($this->having);
                if ($havingSql !== '') {
                    $sql .= " HAVING " . $havingSql;
                }
                $result = [
                    'sql' => $sql,
                    // COUNT does not render the SELECT list, so selectSubquery()
                    // parameters are excluded; FROM/WHERE/HAVING bindings remain.
                    'params' => array_merge($this->fromParams, $this->params, $this->havingParams)
                ];
                break;

            case 'update':
                if (empty($this->setData)) {
                    throw new \InvalidArgumentException('Update data is empty');
                }
                $this->assertBalancedGroups();
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
                $whereSql = $this->renderParts($this->where);
                if ($whereSql !== '') {
                    $sql .= " WHERE " . $whereSql;
                    $params = array_merge($params, $this->params);
                }
                $result = [
                    'sql' => $sql,
                    'params' => $params
                ];
                break;

            case 'delete':
                $this->assertBalancedGroups();
                $tableWithAlias = !empty($this->tableAlias) ? $this->table . ' AS ' . $this->tableAlias : $this->table;
                $sql = "DELETE FROM {$tableWithAlias}";
                $whereSql = $this->renderParts($this->where);
                if ($whereSql !== '') {
                    $sql .= " WHERE " . $whereSql;
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
            'where' => array_column($this->where, 'sql'),
            'joins' => $this->joins,
            'groupBy' => $this->groupBy,
            'orderBy' => $this->orderBy,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'setData' => $this->setData,
        ], $result);

        if ($reset) {
            $this->clearAll();
            // clearAll() intentionally preserves the action (existing behavior),
            // so reset it here to make the opt-in build(true) reuse fully safe.
            $this->action = 'select';
        }

        return $result;
    }

    /**
     * Build a multi-row INSERT statement
     * 
     * @return array{sql: string, params: array<mixed>}
     * @throws \InvalidArgumentException If rows use inconsistent columns
     */
    private function buildInsertBatch() : array {
        $columns = array_keys($this->batchRows[0]);
        foreach ($this->batchRows as $row) {
            if (array_keys($row) !== $columns) {
                throw new \InvalidArgumentException('All rows must contain the same columns');
            }
        }

        $colsSql = implode(', ', $columns);
        $valuesSql = [];
        $params = [];
        foreach ($this->batchRows as $row) {
            $rowSql = [];
            foreach ($columns as $column) {
                $value = $row[$column];
                if ($value instanceof BuilderRaw) {
                    $rowSql[] = $value->value;
                    if ($value->hasBindings()) {
                        $params = array_merge($params, $value->getBindings());
                    }
                } else {
                    $rowSql[] = '?';
                    $params[] = $value;
                }
            }
            $valuesSql[] = '(' . implode(', ', $rowSql) . ')';
        }

        return [
            'sql' => "INSERT INTO {$this->table} ({$colsSql}) VALUES " . implode(', ', $valuesSql),
            'params' => $params
        ];
    }

    /**
     * Build a multi-row INSERT ... ON DUPLICATE KEY UPDATE statement
     * 
     * @return array{sql: string, params: array<mixed>}
     * @throws \InvalidArgumentException If rows use inconsistent columns or all columns are unique keys
     */
    private function buildUpsertBatch() : array {
        $columns = array_keys($this->batchRows[0]);
        foreach ($this->batchRows as $row) {
            if (array_keys($row) !== $columns) {
                throw new \InvalidArgumentException('All rows must contain the same columns');
            }
        }
        $updateColumns = array_values(array_diff($columns, $this->batchUniqueKeys));
        if (empty($updateColumns)) {
            throw new \InvalidArgumentException('upsertBatch() requires at least one non-key column to update');
        }

        $colsSql = implode(', ', $columns);
        $valuesSql = [];
        $params = [];
        foreach ($this->batchRows as $row) {
            $rowSql = [];
            foreach ($columns as $column) {
                $value = $row[$column];
                if ($value instanceof BuilderRaw) {
                    $rowSql[] = $value->value;
                    if ($value->hasBindings()) {
                        $params = array_merge($params, $value->getBindings());
                    }
                } else {
                    $rowSql[] = '?';
                    $params[] = $value;
                }
            }
            $valuesSql[] = '(' . implode(', ', $rowSql) . ')';
        }
        $updateSql = implode(', ', array_map(
            static function (string $column) : string { return "{$column} = VALUES({$column})"; },
            $updateColumns
        ));

        return [
            'sql' => "INSERT INTO {$this->table} ({$colsSql}) VALUES " . implode(', ', $valuesSql)
                . " ON DUPLICATE KEY UPDATE {$updateSql}",
            'params' => $params
        ];
    }

    /**
     * Build a multi-row UPDATE statement using CASE WHEN blocks
     * 
     * @return array{sql: string, params: array<mixed>}
     * @throws \InvalidArgumentException If rows use inconsistent columns or lack the WHERE column
     */
    private function buildUpdateBatch() : array {
        $columns = array_keys($this->batchRows[0]);
        foreach ($this->batchRows as $row) {
            if (array_keys($row) !== $columns) {
                throw new \InvalidArgumentException('All rows must contain the same columns');
            }
            if (!array_key_exists($this->batchWhereColumn, $row)) {
                throw new \InvalidArgumentException(
                    "Every row must contain the WHERE column '{$this->batchWhereColumn}'"
                );
            }
        }

        $setColumns = array_values(array_diff($columns, [$this->batchWhereColumn]));
        if (empty($setColumns)) {
            throw new \InvalidArgumentException(
                'updateBatch() requires at least one column to update besides the WHERE column'
            );
        }

        $params = [];
        $setSql = [];
        foreach ($setColumns as $column) {
            $whenSql = [];
            foreach ($this->batchRows as $row) {
                $whenSql[] = "WHEN {$this->batchWhereColumn} = ? THEN ?";
                $params[] = $row[$this->batchWhereColumn];
                $params[] = $row[$column];
            }
            $setSql[] = "{$column} = CASE " . implode(' ', $whenSql) . ' END';
        }

        $whereValues = [];
        foreach ($this->batchRows as $row) {
            $whereValues[] = $row[$this->batchWhereColumn];
        }
        $wherePlaceholders = implode(', ', array_fill(0, count($whereValues), '?'));

        foreach ($whereValues as $value) {
            $params[] = $value;
        }

        return [
            'sql' => "UPDATE {$this->table} SET " . implode(', ', $setSql)
                . " WHERE {$this->batchWhereColumn} IN ({$wherePlaceholders})",
            'params' => $params
        ];
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
     * Compile a SELECT query into SQL and parameters
     * 
     * Parameters are assembled in SQL order: SELECT subqueries, FROM subquery,
     * WHERE conditions, HAVING conditions, then each UNION part.
     * 
     * @param bool $withOrderLimit Whether to include ORDER BY and LIMIT (false for subqueries and union members)
     * @return array{sql: string, params: array<mixed>}
     * @throws \InvalidArgumentException If condition groups are unbalanced
     */
    private function compileSelect(bool $withOrderLimit = true) : array {
        $this->assertBalancedGroups();
        $table = !empty($this->fromSubquery)
            ? $this->fromSubquery
            : (!empty($this->tableAlias) ? $this->table . ' AS ' . $this->tableAlias : $this->table);

        $sql = "SELECT ";
        if ($this->distinct) {
            $sql .= "DISTINCT ";
        }
        $sql .= "{$this->select} FROM {$table}";

        if (!empty($this->joins)) {
            $sql .= " " . implode(" ", $this->joins);
        }

        $whereSql = $this->renderParts($this->where);
        if ($whereSql !== '') {
            $sql .= " WHERE " . $whereSql;
        }

        if (!empty($this->groupBy)) {
            $sql .= " GROUP BY " . $this->groupBy;
        }

        $havingSql = $this->renderParts($this->having);
        if ($havingSql !== '') {
            $sql .= " HAVING " . $havingSql;
        }

        if ($withOrderLimit && !empty($this->orderBy)) {
            $sql .= " ORDER BY " . $this->orderBy;
        }

        if ($withOrderLimit && $this->limit > 0) {
            $sql .= " LIMIT " . $this->limit;
            if ($this->offset > 0) {
                $sql .= " OFFSET " . $this->offset;
            }
        }

        $params = $this->assembleParams();

        foreach ($this->unions as $union) {
            $compiled = $union['builder']->compileSelect(false);
            $sql .= $union['all'] ? " UNION ALL " : " UNION ";
            $sql .= $compiled['sql'];
            $params = array_merge($params, $compiled['params']);
        }

        return [
            'sql' => $sql,
            'params' => $params
        ];
    }

    /**
     * Assemble all parameters in SQL order
     * 
     * @return array<mixed>
     */
    private function assembleParams() : array {
        return array_merge(
            $this->selectParams,
            $this->fromParams,
            $this->params,
            $this->havingParams
        );
    }

    /**
     * Throw if condition groups are unbalanced
     * 
     * @return void
     * @throws \InvalidArgumentException If condition groups are unbalanced
     */
    private function assertBalancedGroups() : void {
        if ($this->groupDepth !== 0) {
            throw new \InvalidArgumentException(
                "Unbalanced condition groups: {$this->groupDepth} groupStart() call(s) without matching groupEnd()"
            );
        }
    }

    /**
     * Render condition parts into a SQL fragment
     * 
     * Each part carries its join keyword ('AND', 'OR', etc.). Group openers and
     * closers are rendered as parentheses, and the first condition inside a group
     * is emitted without a connector.
     * 
     * @param array<int, array{join: string, sql: string}> $parts
     * @return string
     */
    private function renderParts(array $parts) : string {
        $out = '';
        $suppressJoin = true;
        foreach ($parts as $part) {
            if ($part['sql'] === ')') {
                $out .= ')';
                $suppressJoin = false;
                continue;
            }
            if ($part['sql'] === '(') {
                if (!$suppressJoin) {
                    $out .= ' ' . $part['join'] . ' ';
                } elseif ($part['join'] === 'AND NOT') {
                    $out .= 'NOT ';
                }
                $out .= '(';
                $suppressJoin = true;
                continue;
            }
            if (!$suppressJoin) {
                $out .= ' ' . $part['join'] . ' ';
            }
            $out .= $part['sql'];
            $suppressJoin = false;
        }
        return $out;
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