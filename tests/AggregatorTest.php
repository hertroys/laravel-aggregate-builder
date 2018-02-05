<?php

use Hertroys\Aggregator\Aggregator;
use PHPUnit\Framework\TestCase;

class AggregatorTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        // We'll use Postgres type escaping for these tests.
        // Inject null for pdo since we'll never hit the db.
        $connection = new \Illuminate\Database\PostgresConnection(null);
        $this->agg = (new Aggregator($connection))->table('table');
    }

    protected function assertSql($expected)
    {
        $this->assertEquals($expected, $this->agg->toSql());
    }

    public function test_basic_query()
    {
        $this->assertSql('select * from "table"');
    }

    public function test_auto_alias_aggregate_function()
    {
        $this->agg->sum('col');
        $this->assertSql('select sum("col") as "col_sum" from "table"');
    }

    public function test_auto_alias_count()
    {
        $this->agg->count();
        $this->assertSql('select count(*) as "count" from "table"');
    }

    public function test_alias_aggregate_function()
    {
        $this->agg->sum('col as sum_col');
        $this->assertSql('select sum("col") as "sum_col" from "table"');
    }

    public function test_groupby()
    {
        $this->agg->count()->groupBy('group_id');
        $this->assertSql('select count(*) as "count", "group_id" from "table" group by "group_id"');
    }

    public function test_alias_groupby()
    {
        $this->agg->count()->groupBy('group_id as group');
        $this->assertSql('select count(*) as "count", "group_id" as "group" from "table" group by "group_id"');
    }
}
