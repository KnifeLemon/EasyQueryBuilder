<?php
namespace KnifeLemon\EasyQuery;

/**
 * BuilderRaw - Raw SQL Expression Container
 * 
 * This class represents a raw SQL expression that should be inserted directly
 * into a query without parameter binding. Use with caution - never use with user input.
 * 
 * @package KnifeLemon\EasyQuery
 * @author KnifeLemon
 * @license MIT
 */
class BuilderRaw {
    /**
     * @var string The raw SQL expression
     */
    public $value;
    
    /**
     * @var array<mixed> Bound parameters for the raw expression
     */
    public array $bindings = [];
    
    /**
     * Constructor
     * 
     * @param string $value The raw SQL expression to use
     * @param array<mixed> $bindings Optional bound parameters for placeholders in the expression
     */
    public function __construct(string $value, array $bindings = []) {
        $this->value = $value;
        $this->bindings = $bindings;
    }
    
    /**
     * Check if this raw expression has bound parameters
     * 
     * @return bool
     */
    public function hasBindings(): bool {
        return !empty($this->bindings);
    }
    
    /**
     * Get bound parameters
     * 
     * @return array<mixed>
     */
    public function getBindings(): array {
        return $this->bindings;
    }
    
    /**
     * Validate and sanitize a column/table identifier
     * 
     * Only allows alphanumeric characters, underscores, and dots (for table.column notation).
     * Use this when the column name comes from user input.
     * 
     * @param string $identifier The column or table name to validate
     * @return string Validated identifier
     * @throws \InvalidArgumentException If identifier contains invalid characters
     * 
     * Example:
     * $safeColumn = BuilderRaw::safeIdentifier($userInput);
     * Builder::raw("COALESCE(SUM({$safeColumn}), 0)")
     */
    public static function safeIdentifier(string $identifier): string {
        // Only allow alphanumeric, underscore, and dot (for table.column)
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $identifier)) {
            throw new \InvalidArgumentException(
                "Invalid identifier: '{$identifier}'. Only alphanumeric characters, underscores, and dots are allowed."
            );
        }
        return $identifier;
    }
    
    /**
     * Create a raw expression with safe identifier substitution
     * 
     * Replaces {column} placeholders with validated identifiers.
     * Use this when building raw SQL with user-provided column names.
     * 
     * @param string $expression SQL expression with {placeholder} markers for identifiers
     * @param array<string, string> $identifiers Associative array ['placeholder' => 'column_name']
     * @param array<mixed> $bindings Optional value bindings for ? placeholders
     * @return self
     * @throws \InvalidArgumentException If any identifier is invalid
     * 
     * Example:
     * BuilderRaw::withIdentifiers(
     *     'COALESCE(SUM({col}), ?)',
     *     ['col' => $userInputColumn],
     *     [0]
     * )
     */
    public static function withIdentifiers(string $expression, array $identifiers, array $bindings = []): self {
        foreach ($identifiers as $placeholder => $identifier) {
            $safeIdentifier = self::safeIdentifier($identifier);
            $expression = str_replace("{{$placeholder}}", $safeIdentifier, $expression);
        }
        return new self($expression, $bindings);
    }
}
