<?php
declare(strict_types=1);

/*
 * Global helper aliases for the theme layer (global namespace).
 * The main helpers live in the MyTDX namespace (src/Helpers.php, src/bootstrap.php);
 * these thin wrappers let themes keep the classic myTinyTodo __()/_e() syntax.
 */

if (!function_exists('__')) {
    function __(string $key): string
    {
        return \MyTDX\__($key);
    }
}

if (!function_exists('_e')) {
    function _e(string $key): void
    {
        \MyTDX\_e($key);
    }
}