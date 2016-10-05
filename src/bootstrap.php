<?php
// Глобальный перехватчик для исключений, которые не будут пойманы в контексте
set_exception_handler(['engine\Handlers', 'exceptionHandler']);

// Перехват ошибок PHP
set_error_handler(['engine\Handlers', 'errorHandler']);

// Включаем буферизацию вывода. На завершении программы выполняется моя функция, в ней задана выдача буфера.
// Подробнее см. в доке "Перехват ошибок".
ob_start();
register_shutdown_function(['engine\Handlers', 'shutdown']);

/**
 * Упростил себе жизнь. Заколебался писать FQN-путь к методу. Не по "фен-шую" конечно, но для приложения покатит.
 * Теперь вызов простой: dd($var1, $var2, ...);
 */
if (!function_exists('dd')) {
    function dd()
    {
        foreach (func_get_args() as $var) {
            echo engine\utils\Dumper::dump($var);
        }
    }
}
