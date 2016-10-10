<?php
namespace kira\exceptions;

/**
 * Исключение указыает на ошибку записи
 *
 * Пока использую общий смысл: запись в файл, перезапись значения в Реестре и т.п. Если потребуется конкретизация, тогда
 * либо введу коды исключения через константы либо новые исключения. Есть аналог - ReadException
 */
class WriteException extends \Exception
{
}