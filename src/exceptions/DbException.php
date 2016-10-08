<?php
namespace kira\exceptions;

/**
 * Исключение для функционала работы с базой данных. Модель и подключение к БД пробрасывают одинаковое исключение.
 */
class DbException extends \PDOException
{
}
