<?php

namespace Hertroys\Aggregator;

class Aggregator
{
    public $query;

    protected $groups = [];

    public function table($table)
    {
        $this->newQuery($table);

        return $this;
    }

    public function newQuery($on)
    {
        $this->query = app('db')->table($on);

        return $this;
    }

    public function get() // :Illuminate\Support\Collection
    {
        $this->regroup();

        return $this->query->get();
        // Regrettably, we cannot renew and repopulate the query,
        // since we don't track all the where's and other modifiers.
        // For rollup/cube we only manage groups and group aliasses.
    }

    public function count($column = '*', $as = null)
    {
        return $this->aggregate(__FUNCTION__, $column, $as);
    }

    public function min($column, $as = null)
    {
        return $this->aggregate(__FUNCTION__, $column, $as);
    }

    public function max($column, $as = null)
    {
        return $this->aggregate(__FUNCTION__, $column, $as);
    }

    public function sum($column, $as = null)
    {
        return $this->aggregate(__FUNCTION__, $column, $as);
    }

    public function avg($column, $as = null)
    {
        return $this->aggregate(__FUNCTION__, $column, $as);
    }

    public function average($column, $as = null)
    {
        return $this->avg($column, $as);
    }

    protected function aggregate($function, $column, $as)
    {
        $alias = $this->wrap($as ?: $this->alias($function, $column));

        $column = $this->wrap($column);

        $this->addSelect(app('db')->raw("$function($column) as $alias"));

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

    public function by($column, $as = null)
    {
        $group = [$column, implode(array_filter(func_get_args()), ' as ')];
        $this->groups[] = $group;

        return $this;
    }

    // If "n" is the number of columns listed in the ROLLUP,
    // there will be n+1 levels of subtotals
    public function rollup() // :Illuminate\Support\Collection
    {
        $result[] = $this->get();

        foreach (array_reverse($this->groups) as $group) {

            $this->removeGroup(...$group);

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

    protected function addGroup($column, $as)
    {
        $this->query->groupBy($column)->addSelect($as);
    }

    protected function removeGroup($column, $as)
    {
        $this->unsetArrayValue($this->query->groups, $column);
        $this->unsetArrayValue($this->query->columns, $as);

        if (count($this->query->groups) === 0) {
            $this->query->groups = null;
        }
        // The Illuminate query builder adds an invalid group by clause
        // if the groups-attribute is an empty array instead of null.
    }

    protected function addGroups($groups)
    {
        foreach ($groups as $group) {
            $this->addGroup(...$group);
        }
    }

    protected function removeGroups($groups)
    {
        foreach ($groups as $group) {
            $this->removeGroup(...$group);
        }
    }

    protected function regroup()
    {
        $this->removeGroups($this->groups);
        $this->addGroups($this->groups);
    }

    public function fresh()
    {
        $this->newQuery($this->query->from);
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
