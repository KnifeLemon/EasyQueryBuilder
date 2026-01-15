<?php
namespace KnifeLemon\GenerateQuery\Tests;

use KnifeLemon\GenerateQuery\GenerateQueryRaw;
use PHPUnit\Framework\TestCase;

class GenerateQueryRawTest extends TestCase
{
    /**
     * Test creating a raw SQL expression
     */
    public function testCreateRawExpression(): void
    {
        $raw = new GenerateQueryRaw('NOW()');
        
        $this->assertInstanceOf(GenerateQueryRaw::class, $raw);
        $this->assertEquals('NOW()', $raw->value);
    }

    /**
     * Test raw SQL with function call
     */
    public function testRawWithFunction(): void
    {
        $raw = new GenerateQueryRaw('UPPER(name)');
        
        $this->assertEquals('UPPER(name)', $raw->value);
    }

    /**
     * Test raw SQL with arithmetic expression
     */
    public function testRawWithArithmetic(): void
    {
        $raw = new GenerateQueryRaw('price * 1.1');
        
        $this->assertEquals('price * 1.1', $raw->value);
    }

    /**
     * Test raw SQL with subquery
     */
    public function testRawWithSubquery(): void
    {
        $raw = new GenerateQueryRaw('(SELECT AVG(price) FROM products)');
        
        $this->assertEquals('(SELECT AVG(price) FROM products)', $raw->value);
    }

    /**
     * Test raw SQL with CASE statement
     */
    public function testRawWithCaseStatement(): void
    {
        $raw = new GenerateQueryRaw("CASE WHEN status = 'active' THEN 1 ELSE 0 END");
        
        $this->assertEquals("CASE WHEN status = 'active' THEN 1 ELSE 0 END", $raw->value);
    }

    /**
     * Test public value property is accessible
     */
    public function testValuePropertyIsAccessible(): void
    {
        $raw = new GenerateQueryRaw('COUNT(*)');
        
        $this->assertObjectHasProperty('value', $raw);
        $this->assertEquals('COUNT(*)', $raw->value);
    }

    /**
     * Test raw SQL with multiple expressions
     */
    public function testRawWithMultipleExpressions(): void
    {
        $raw = new GenerateQueryRaw('GREATEST(0, points - 100)');
        
        $this->assertEquals('GREATEST(0, points - 100)', $raw->value);
    }

    /**
     * Test raw SQL with complex expression
     */
    public function testRawWithComplexExpression(): void
    {
        $raw = new GenerateQueryRaw('DATE_FORMAT(created_at, "%Y-%m-%d")');
        
        $this->assertEquals('DATE_FORMAT(created_at, "%Y-%m-%d")', $raw->value);
    }
}
