<?php
namespace kira\exceptions;

/**
 * Исключение указывает на ошибку чтения
 *
 * Пока использую общий смысл: чтение из файла, получение значения из класса Реестра и т.п. Если потребуется
 * конкретизация, тогда либо введу коды исключения через константы либо новые исключения. Есть аналог - WriteException
 */
class ReadException extends EngineException
{
}
