<?php
/**
 * Начальный загрузчик движка
 *
 * Его вызов гарантирован Composer, см. в composer.json секцию autoload > files
 */

require 'shortcuts.php';

// Глобальный перехватчик для исключений, которые не будут пойманы в контексте
set_exception_handler(['kira\Handlers', 'exceptionHandler']);

// Перехват ошибок PHP
set_error_handler(['kira\Handlers', 'errorHandler']);

// Включаем буферизацию вывода. На завершении программы выполняется моя функция, в ней задана выдача буфера.
// Подробнее см. в доке "Перехват ошибок".
ob_start();
register_shutdown_function(['kira\Handlers', 'shutdown']);

// Фикс бага PHP >= 7.1 по части сериализации чисел с плавающей точкой
// @see https://stackoverflow.com/a/43056278/5497749
if (version_compare(phpversion(), '7.1', '>=')) {
    ini_set('serialize_precision', -1);
}
