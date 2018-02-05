<?php

namespace Hertroys\Aggregator;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

class Aggregator
{
    public $query;

    protected $groups = [];

    public function __construct(Connection $connection)
    {
        $this->query = $connection->query();
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function table($table)
    {
        $this->query->from($table);

        return $this;
    }

    public function count($column = '*')
    {
        return $this->aggregate(__FUNCTION__, $column);
    }

    public function min($column)
    {
        return $this->aggregate(__FUNCTION__, $column);
    }

    public function max($column)
    {
        return $this->aggregate(__FUNCTION__, $column);
    }

    public function sum($column)
    {
        return $this->aggregate(__FUNCTION__, $column);
    }

    public function avg($column)
    {
        return $this->aggregate(__FUNCTION__, $column);
    }

    public function average($column)
    {
        return $this->avg($column);
    }

    protected function aggregate($function, $column)
    {
        $segments = explode(' as ', $column);
        $alias = count($segments) > 1 ? end($segments)
            : $this->alias($function, $column);

        $name = $this->wrap($segments[0]);
        $alias = $this->wrap($alias);

        $this->addSelect($this->query->raw("$function($name) as $alias"));

        return $this;
    }

    protected function alias($function, $column = null)
    {
        return str_replace('*_', '', $column.'_'.$function);
    }

    public function wrap($value)
    {
        return $this->query->getGrammar()->wrap($value);
    }

    public function groupBy(...$groups)
    {
        array_walk($groups, [$this, 'addGroup']);

        return $this;
    }

    public function clear()
    {
        array_walk($this->groups, [$this, 'removeGroup']);

        return $this;
    }

    protected function addGroup($group)
    {
        $this->groups[] = $group;

        $segments = explode(' as ', $group);

        $this->query->groupBy($segments[0])->addSelect($group);
    }

    protected function removeGroup($group)
    {
        array_unset_value($this->groups, $group);

        $segments = explode(' as ', $group);

        array_unset_value($this->query->groups, $segments[0]);
        array_unset_value($this->query->columns, $group);

        if (count($this->query->groups) === 0) {
            $this->query->groups = null;
        }
        // The Illuminate query builder adds an invalid group by clause
        // if the groups-attribute is an empty array instead of null.
    }

    // If "n" is the number of columns listed in the ROLLUP,
    // there will be n+1 levels of subtotals
    // A, B, C => [[A,B,C], [A,B], [A], []]
    public function rollup() :\Illuminate\Support\Collection
    {
        $result[] = $this->get();

        foreach (array_reverse($this->groups) as $group) {
            $this->removeGroup($group);
            $result[] = $this->get();
        }

        return collect($result);
    }

    // If "n" is the number of columns listed in the CUBE,
    // there will be 2^n subtotal combinations.
    // A, B, C => [[A,B,C], [A,B], [A,C], [A], [B,C], [B], [C], []]
    public function cube() :\Illuminate\Support\Collection
    {
        $combinations = array_pow($this->groups);

        foreach ($combinations as $combo) {
            $this->clear()->groupBy(...$combo);
            $result[] = $this->get();
        }

        return collect($result);
    }

    public function __call($method, $parameters)
    {
        $return = $this->query->$method(...$parameters);

        if (is_null($return) ||
            (is_object($return) && get_class($return) === Builder::class)
        ) {
            return $this;
        }

        return $return;
    }
}
