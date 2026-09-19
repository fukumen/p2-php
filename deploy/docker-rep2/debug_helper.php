<?php
/*
 * Debug helper
 * Intended for use in watch expressions and the debug console
 */

if (!function_exists('sd')) {
    // Convert CP932 to UTF-8
    function sd($var) {
        $str = print_r($var, true);
        return mb_convert_encoding($str, 'UTF-8', 'CP932');
    }
}
