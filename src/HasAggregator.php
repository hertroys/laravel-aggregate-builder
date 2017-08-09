<?php

namespace Hertroys\Aggregator;

trait HasAggregator
{
    public static function aggregator()
    {
        $instance = new static;

        return $instance->getAggregator();
    }

    public static function agg()
    {
        return static::aggregator();
    }

    public function getAggregator()
    {
        $aggregator = new Aggregator;

        return $aggregator->table($this->getTable());
    }
}
