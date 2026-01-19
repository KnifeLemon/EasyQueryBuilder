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

    /**
     * Test raw SQL with bindings
     */
    public function testRawWithBindings(): void
    {
        $raw = new BuilderRaw('COALESCE(amount, ?)', [0]);
        
        $this->assertEquals('COALESCE(amount, ?)', $raw->value);
        $this->assertTrue($raw->hasBindings());
        $this->assertEquals([0], $raw->getBindings());
    }

    /**
     * Test raw SQL without bindings returns empty array
     */
    public function testRawWithoutBindingsReturnsEmptyArray(): void
    {
        $raw = new BuilderRaw('NOW()');
        
        $this->assertFalse($raw->hasBindings());
        $this->assertEquals([], $raw->getBindings());
    }

    /**
     * Test safeIdentifier with valid column name
     */
    public function testSafeIdentifierWithValidColumn(): void
    {
        $this->assertEquals('total_amount', BuilderRaw::safeIdentifier('total_amount'));
        $this->assertEquals('users', BuilderRaw::safeIdentifier('users'));
        $this->assertEquals('_private', BuilderRaw::safeIdentifier('_private'));
        $this->assertEquals('column123', BuilderRaw::safeIdentifier('column123'));
    }

    /**
     * Test safeIdentifier with table.column notation
     */
    public function testSafeIdentifierWithTableColumn(): void
    {
        $this->assertEquals('users.name', BuilderRaw::safeIdentifier('users.name'));
        $this->assertEquals('orders.total_amount', BuilderRaw::safeIdentifier('orders.total_amount'));
    }

    /**
     * Test safeIdentifier throws exception for invalid characters
     */
    public function testSafeIdentifierThrowsExceptionForInvalidChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BuilderRaw::safeIdentifier('column; DROP TABLE users--');
    }

    /**
     * Test safeIdentifier throws exception for SQL injection attempt
     */
    public function testSafeIdentifierThrowsExceptionForSqlInjection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BuilderRaw::safeIdentifier("column' OR '1'='1");
    }

    /**
     * Test safeIdentifier throws exception for spaces
     */
    public function testSafeIdentifierThrowsExceptionForSpaces(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BuilderRaw::safeIdentifier('column name');
    }

    /**
     * Test withIdentifiers creates safe raw expression
     */
    public function testWithIdentifiersCreatesSafeRaw(): void
    {
        $raw = BuilderRaw::withIdentifiers(
            'COALESCE(SUM({col}), ?)',
            ['col' => 'total_amount'],
            [0]
        );
        
        $this->assertEquals('COALESCE(SUM(total_amount), ?)', $raw->value);
        $this->assertEquals([0], $raw->getBindings());
    }

    /**
     * Test withIdentifiers with multiple placeholders
     */
    public function testWithIdentifiersMultiplePlaceholders(): void
    {
        $raw = BuilderRaw::withIdentifiers(
            '{table}.{col1} + {table}.{col2}',
            ['table' => 'orders', 'col1' => 'price', 'col2' => 'tax']
        );
        
        $this->assertEquals('orders.price + orders.tax', $raw->value);
    }

    /**
     * Test withIdentifiers throws exception for invalid identifier
     */
    public function testWithIdentifiersThrowsExceptionForInvalidIdentifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BuilderRaw::withIdentifiers(
            'SELECT {col} FROM users',
            ['col' => 'name; DROP TABLE users--']
        );
    }
}
