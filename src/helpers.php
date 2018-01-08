<?php

if (! function_exists('array_unset_value')) {

    function array_unset_value(&$array, $value)
    {
        if (($key = array_search($value, (array)$array)) !== false) {
            unset($array[$key]);
        }
    }
}
