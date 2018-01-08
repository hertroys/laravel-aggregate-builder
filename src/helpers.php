<?php

if (! function_exists('array_unset_value')) {

    function array_unset_value(&$array, $value)
    {
        if (($key = array_search($value, (array)$array)) !== false) {
            unset($array[$key]);
        }
    }
}

if (! function_exists('array_pow')) {

    function array_pow($array)
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
}
