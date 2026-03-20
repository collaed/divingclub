<?php

// Polyfill for mb_split — missing from Wasmer PHP WASI (no mbregex)
if (! function_exists('mb_split')) {
    function mb_split(string $pattern, string $string, int $limit = -1): array|false
    {
        return preg_split('/'.$pattern.'/u', $string, $limit) ?: false;
    }
}
