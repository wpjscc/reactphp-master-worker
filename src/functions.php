<?php

if (!function_exists('getParam')) {
    function getParam($key, $default = null) {
        foreach ($GLOBALS['argv'] as $arg) {
            if (strpos($arg, $key) !==false) {
                return explode('=', $arg)[1];
            }
        }
        return $default;
    }
}