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
     * Constructor
     * 
     * @param string $value The raw SQL expression to use
     */
    public function __construct(string $value) {
        $this->value = $value;
    }
}
