<?php
namespace KnifeLemon\EasyQuery\Tests;

use KnifeLemon\EasyQuery\Builder;
use KnifeLemon\EasyQuery\QueryLogger;
use KnifeLemon\EasyQuery\QueryPanel;
use PHPUnit\Framework\TestCase;

class QueryPanelTest extends TestCase
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
     * Test getTab() reflects the total query count
     */
    public function testGetTabShowsTotalQueryCount(): void
    {
        $panel = new QueryPanel();

        if (!QueryLogger::isEnabled()) {
            $this->assertSame('', $panel->getTab());
            return;
        }

        $this->assertStringContainsString('SQL: 0', $panel->getTab());

        Builder::table('users')->where(['id' => 1])->build();

        $this->assertStringContainsString('SQL: 1', $panel->getTab());
    }

    /**
     * Test getPanel() reports when logging is disabled
     */
    public function testGetPanelWhenDisabled(): void
    {
        if (QueryLogger::isEnabled()) {
            $this->markTestSkipped('Tracy is available, so logging is enabled');
        }

        $this->assertStringContainsString('Logger not enabled', (new QueryPanel())->getPanel());
    }

    /**
     * Test getPanel() renders summary cards, SQL, params and detail rows
     */
    public function testGetPanelRendersLoggedQueries(): void
    {
        if (!QueryLogger::isEnabled()) {
            $this->markTestSkipped('Tracy is not available, so logging is disabled');
        }

        Builder::table('users', 'u')
            ->select(['u.id', 'u.name'])
            ->innerJoin('posts', 'u.id = p.user_id', 'p')
            ->where(['u.status' => 'active'])
            ->groupBy('u.id')
            ->orderBy('u.name DESC')
            ->limit(10)
            ->build();

        Builder::table('users')->insert(['name' => 'Alice'])->build();

        $html = (new QueryPanel())->getPanel();

        $this->assertStringContainsString('EasyQuery - SQL Builder', $html);
        $this->assertStringContainsString('Total Queries', $html);
        $this->assertStringContainsString('SELECT u.id, u.name FROM users AS u', $html);
        $this->assertStringContainsString('active', $html);
        $this->assertStringContainsString('Joins:', $html);
        $this->assertStringContainsString('Group By:', $html);
        $this->assertStringContainsString('Order By:', $html);
        $this->assertStringContainsString('Limit:', $html);
        $this->assertStringContainsString('Set Data:', $html);
        $this->assertStringContainsString('Select:', $html);
    }
}
