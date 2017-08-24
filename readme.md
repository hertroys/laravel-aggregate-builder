# Aggregate builder

The laravel-aggregate-builder simplifies building aggregating queries.

```
$agg = new \Hertroys\Aggregator\Aggregator;

$agg->table('people')
    ->min('age')
    ->max('age')
    ->avg('age')
    ->by('gender_id')
    ->get();
```

It can be used in the same way as the standard [Laravel query builder](http://laravel.com/docs/queries), with `where`, `orderBy` and `join` clauses, and so on. It only overrides the way the aggregate functions (`count`, `min`, `max`, `sum`, `avg`) are used. The underlying query builder can be retrieved with `->getQuery()`

## Aggregate functions
The aggregate-builder facilitates chaining aggregate functions. The query result is returned with `->get()`.

```
$agg->table('people')->min('age')->max('age')->avg('age')->get();

```

Output:

```
Illuminate\Support\Collection Object
(
    [items:protected] => Array
        (
            [0] => stdClass Object
                (
                    [age_min] => 6
                    [age_max] => 100
                    [age_avg] => 48.1400
                )

        )

)
```

## groupBy
The Laravel query builder does not support adding a groupBy to an aggregate function and you might find yourself using constructs like

`DB::table('people')->select(DB::raw('avg(age) as age_avg'))->groupBy('gender_id')->addSelect('gender_id')->get()`

The aggregate builder offers the `by` shorthand which

- adds the group, like `->groupBy('column')`
- and the select, like `->addSelect('column')`

```
$agg->table('people')->avg('age')->by('gender_id')->get();
```

Output:
```
Illuminate\Support\Collection Object
(
    [items:protected] => Array
        (
            [0] => stdClass Object
                (
                    [age_avg] => 54.8889
                    [gender_id] => 1
                )

            [1] => stdClass Object
                (
                    [age_avg] => 42.3684
                    [gender_id] => 2
                )

            [2] => stdClass Object
                (
                    [age_avg] => 47.2308
                    [gender_id] => 3
                )

        )

)
```

## Rollup and Cube
Besides `->get()` to retrieve the result, the aggregate-builder exposes the `rollup` and `cube` functions. These should be used in conjunction with the `->by()` shorthand and return a collection of collections, the first being the same result as from `get`, the others being subtotals over the different groups. They emulate the operations in [Oracle](https://oracle-base.com/articles/misc/rollup-cube-grouping-functions-and-grouping-sets) and [SQL Server](https://technet.microsoft.com/en-us/library/bb522495(v=sql.105).aspx).

### Rollup
With *n* groups, `rollup` returns *n*+1 collections, with group subtotals from right to left
`->by(a)->by(b)->by(c)->rollup()`

1. (a, b, c)
2. (a, b)
3. (a)
4. ()

```
$agg->table('people')
    ->by('gender_id')
    ->by('hair_colour_id')
    ->by('eye_colour_id')
    ->count()
    ->rollup();
```

Queries:
```
1 select count(*) as `count`, `gender_id`, `hair_colour_id`, `eye_colour_id` from `people` group by `gender_id`, `hair_colour_id`, `eye_colour_id`
2 select count(*) as `count`, `gender_id`, `hair_colour_id` from `people` group by `gender_id`, `hair_colour_id`
3 select count(*) as `count`, `gender_id` from `people` group by `gender_id`
4 select count(*) as `count` from `people`
```

### Cube
With *n>0* groups, `cube` returns 2^*n* collections, with group subtotals for every combination in the following order

`->by(a)->by(b)->by(c)->cube()`

1. (a, b, c)
2. (a, b)
3. (a, c)
4. (a)
5. (b, c)
6. (b)
7. (c)
8. ()

This is especially convenient for two-dimensional tables. The cells are returned in the first collection, the subtotals for the x-axis in the second, the subtotals for the y-axis in the third, and the grand total in the fourth.

```
$agg->table('people')
    ->by('gender_id')
    ->by('hair_colour_id')
    ->count()
    ->cube();
```

Queries:
```
1 select count(*) as `count`, `gender_id`, `hair_colour_id` from `people` group by `gender_id`, `hair_colour_id`
2 select count(*) as `count`, `gender_id` from `people` group by `gender_id`
3 select count(*) as `count`, `hair_colour_id` from `people` group by `hair_colour_id`
4 select count(*) as `count` from `people`
```

## Eloquent
The `HasAggregator` trait adds the static function `aggregator` to an eloquent class. This returns the aggregate builder and sets the table.

```
$agg = \App\Person::aggregator();

$agg->count()->get();
```
