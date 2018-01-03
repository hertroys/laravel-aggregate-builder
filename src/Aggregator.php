<?php

namespace Hertroys\Aggregator;

class Aggregator
{
    public $query;

    protected $groups = [];

    public function table($table)
    {
        $this->query = app('db')->table($table);

        return $this;
    }

    public function get()
    {
        return $this->query->get();
    }

    public function toSql()
    {
        return $this->query->toSql();
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
        $alias = count($segments) > 1 ? end($segments) : $this->alias($function, $segments[0]);

        $name = $this->wrap($segments[0]);
        $alias = $this->wrap($alias);

        $this->addSelect(app('db')->raw("$function($name) as $alias"));

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

    // If "n" is the number of columns listed in the ROLLUP,
    // there will be n+1 levels of subtotals
    public function rollup() // :Illuminate\Support\Collection
    {
        $result[] = $this->get();

        foreach (array_reverse($this->groups) as $group) {

            $this->removeGroup($group);

            $result[] = $this->query->get();
        }

        return collect($result);
    }

    // If "n" is the number of columns listed in the CUBE,
    // there will be 2^n subtotal combinations.
    // A, B, C => [[A,B,C], [A,B], [A,C], [A], [B,C], [B], [C], []]
    public function cube() // :Illuminate\Support\Collection
    {
        // Ideal for tables, if one puts one dimension on the x-axis and
        // antoher on the y-axis, the subtotals for x are the 2nd element,
        // the subtotals for y are the 3d and the grand total is the 4th.
        // X, Y => [[X,Y], [X], [Y], []]

        $combinations = $this->cubeCombine($this->groups);

        foreach ($combinations as $combo) {
            $this->removeGroups($this->groups);
            $this->addGroups($combo);

            $result[] = $this->query->get();
        }

        return collect($result);
    }

    protected function addGroup($group)
    {
        $this->groups[] = $group;

        $segments = explode(' as ', $group);

        $this->query->groupBy($segments[0])->addSelect($group);
    }

    protected function removeGroup($group)
    {
        $this->unsetArrayValue($this->groups, $group);

        $segments = explode(' as ', $group);

        $this->unsetArrayValue($this->query->groups, $segments[0]);
        $this->unsetArrayValue($this->query->columns, $group);

        if (count($this->query->groups) === 0) {
            $this->query->groups = null;
        }
        // The Illuminate query builder adds an invalid group by clause
        // if the groups-attribute is an empty array instead of null.
    }

    protected function addGroups($groups)
    {
        array_walk($groups, [$this, 'addGroup']);
    }

    protected function removeGroups($groups)
    {
        array_walk($groups, [$this, 'removeGroup']);
    }

    public function fresh()
    {
        $this->table($this->query->from);
        $this->groups = [];

        return $this;
    }

    public function flip()
    {
        $this->groups = array_reverse($this->groups);

        return $this;
    }

    public function setGroups($groups)
    {
        $this->groups = $groups;

        return $this;
    }

    public function getGroups()
    {
        return $this->groups;
    }

    public function getQuery()
    {
        return $this->query;
    }

    protected function unsetArrayValue(&$array, $value)
    {
        if (($key = array_search($value, (array)$array)) !== false) {
            unset($array[$key]);
        }
    }

    protected function cubeCombine($array)
    {
        $count = count($array);
        $members = pow(2, $count);

        for ($i = 0; $i < $members; $i++) {
            $b = sprintf("%0".$count."b", $i);
            // 'b' = binary representation
            // so, 0 => 00, 1 => 01, 2 => 10, &c.
            $combo = [];

            for ($j = 0; $j < $count; $j++) {
                if ($b{$j} == '1') {
                    $combo[] = $array[$j];
                }
            }
            $combos[] = $combo;
        }

        return array_reverse($combos);
    }

    public function __call($method, $parameters)
    {
        $this->query->$method(...$parameters);

        return $this;
    }
}
