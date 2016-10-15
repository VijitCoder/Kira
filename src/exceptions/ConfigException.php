<?php
namespace kira\exceptions;

/**
 * Исключение указывает на ошибку чтения конфигурации приложения.
 *
 * Кидается им исключительно класс kira\core\App, когда не может найти требуемую настройку в приложении.
 */
class ConfigException extends \Exception
{
}
