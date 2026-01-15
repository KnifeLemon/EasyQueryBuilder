<?php
namespace KnifeLemon\EasyQuery\Tests;

use KnifeLemon\EasyQuery\BuilderRaw;
use PHPUnit\Framework\TestCase;

class BuilderRawTest extends TestCase
{
    /**
     * Test creating a raw SQL expression
     */
    public function testCreateRawExpression(): void
    {
        $raw = new BuilderRaw('NOW()');
        
        $this->assertInstanceOf(BuilderRaw::class, $raw);
        $this->assertEquals('NOW()', $raw->value);
    }

    /**
     * Test raw SQL with function call
     */
    public function testRawWithFunction(): void
    {
        $raw = new BuilderRaw('UPPER(name)');
        
        $this->assertEquals('UPPER(name)', $raw->value);
    }

    /**
     * Test raw SQL with arithmetic expression
     */
    public function testRawWithArithmetic(): void
    {
        $raw = new BuilderRaw('price * 1.1');
        
        $this->assertEquals('price * 1.1', $raw->value);
    }

    /**
     * Test raw SQL with subquery
     */
    public function testRawWithSubquery(): void
    {
        $raw = new BuilderRaw('(SELECT AVG(price) FROM products)');
        
        $this->assertEquals('(SELECT AVG(price) FROM products)', $raw->value);
    }

    /**
     * Test raw SQL with CASE statement
     */
    public function testRawWithCaseStatement(): void
    {
        $raw = new BuilderRaw("CASE WHEN status = 'active' THEN 1 ELSE 0 END");
        
        $this->assertEquals("CASE WHEN status = 'active' THEN 1 ELSE 0 END", $raw->value);
    }

    /**
     * Test public value property is accessible
     */
    public function testValuePropertyIsAccessible(): void
    {
        $raw = new BuilderRaw('COUNT(*)');
        
        $this->assertObjectHasProperty('value', $raw);
        $this->assertEquals('COUNT(*)', $raw->value);
    }

    /**
     * Test raw SQL with multiple expressions
     */
    public function testRawWithMultipleExpressions(): void
    {
        $raw = new BuilderRaw('GREATEST(0, points - 100)');
        
        $this->assertEquals('GREATEST(0, points - 100)', $raw->value);
    }

    /**
     * Test raw SQL with complex expression
     */
    public function testRawWithComplexExpression(): void
    {
        $raw = new BuilderRaw('DATE_FORMAT(created_at, "%Y-%m-%d")');
        
        $this->assertEquals('DATE_FORMAT(created_at, "%Y-%m-%d")', $raw->value);
    }
}
