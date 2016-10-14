<?php
if (!function_exists('isConsoleInterface')) {
    /**
     * Проверка, что PHP запущен из консоли
     * @return bool
     */
    function isConsoleInterface()
    {
        return (php_sapi_name() === 'cli');
        // return !isset($_SERVER['REQUEST_URI']); // еще вариант
    }
}

if (!function_exists('dd')) {
    /**
     * Короткий вызвов для дампа переменной через kira\utils\Dumper::dump()
     *
     * Упростил себе жизнь. Заколебался писать FQN-путь к методу. Теперь вызов простой: dd($var1, $var2, ...);
     */
    function dd()
    {
        foreach (func_get_args() as $var) {
            echo isConsoleInterface()
                ? kira\utils\Dumper::dumpAsString($var, 10, 0) . PHP_EOL
                : kira\utils\Dumper::dump($var);
        }
    }
}
