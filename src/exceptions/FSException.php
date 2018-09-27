<?php
namespace kira\exceptions;

/**
 * Исключение указывает на ошибки при использовании utils\FS
 *
 * Код ошибки, передаваемый в конструктор, соответствует уровням ошибок PHP
 * {@link http://php.net/manual/ru/errorfunc.constants.php}
 */
class FSException extends EngineException
{
}
