<?php
namespace kira\utils;

/**
 * Собрание функций, относящихся к самому PHP - "системные" функции.
 */
class System
{
    /**
     * Проверка, что PHP запущен из консоли
     * @return bool
     */
    public static function isConsoleInterface()
    {
        return (php_sapi_name() === 'cli');
        // return !isset($_SERVER['REQUEST_URI']); // еще вариант
    }

    /**
     * Обертка для перехвата PHP ошибок и превращения их в исключение
     *
     * По мотивам {@link http://stackoverflow.com/questions/1241728/can-i-try-catch-a-warning}
     *
     * @param string                $exception FQN исключения, которое пробрасывать в случае перехвата ошибок
     * @param callable|array|string $function  функция, которую нужно вызвать в обертке
     * @param array                 ...$args   аргументы для передачи в $function
     * @return mixed результат выполнения $function
     */
    public static function errorWrapper(string $exception, $function, ...$args)
    {
        set_error_handler(function (int $errLevel, string $message, string $file, int $line) use ($exception) {
            throw new $exception($message . PHP_EOL . "Источник: {$file}:{$line}", $errLevel);
        });

        try {
            $result = is_callable($function) ? $function(...$args) : call_user_func($function, ...$args);
        } finally {
            restore_error_handler();
        }

        return $result;
    }
}
