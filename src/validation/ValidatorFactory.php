<?php
namespace kira\validation;

use kira\core\App;
use kira\utils\StringCase;

/**
 * Фабрика получения экземпляров классов валидаторов
 */
class ValidatorFactory
{
    /**
     * Пространство имен классов валидаторов
     */
    const VALIDATORS_NS = '\kira\validation\validators\\';

    /**
     * Получаем экземпляр класса валидатора по названию валидатора
     * @param string $name    название валидатора. Почти совпадает с именем класса.
     * @param mixed  $options параметры, передаваемые в класс-валидатор
     * @return validators\AbstractValidator|null
     */
    public function makeValidator(string $name, $options)
    {
        $class = self::VALIDATORS_NS . StringCase::snakeToCamel($name);
        return App::composer()->findFile($class) ? new $class($options) : null;
    }
}
