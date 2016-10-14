<?php
// Глобальный перехватчик для исключений, которые не будут пойманы в контексте
set_exception_handler(['kira\Handlers', 'exceptionHandler']);

// Перехват ошибок PHP
set_error_handler(['kira\Handlers', 'errorHandler']);

// Включаем буферизацию вывода. На завершении программы выполняется моя функция, в ней задана выдача буфера.
// Подробнее см. в доке "Перехват ошибок".
ob_start();
register_shutdown_function(['kira\Handlers', 'shutdown']);

require 'system_functions.php';
