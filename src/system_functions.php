<?php
use kira\utils\System;

if (!function_exists('dd')) {
    /**
     * Короткий вызов для дампа переменной через kira\utils\Dumper::dump()
     *
     * Упростил себе жизнь. Заколебался писать FQN-путь к методу. Теперь вызов простой: dd($var1, $var2, ...);
     */
    function dd()
    {
        foreach (func_get_args() as $var) {
            if (System::isConsoleInterface()) {
                echo kira\utils\Dumper::dumpAsString($var, 10, 0) . PHP_EOL;
            } else {
                kira\utils\Dumper::dump($var);
            }
        }
    }
}
