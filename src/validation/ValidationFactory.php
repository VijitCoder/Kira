<?php

namespace kira\validation;

use kira\core\App;
use kira\utils\StringCase;
use kira\web\Env;

/**
 * Фабрика получения экземпляров классов, задействованных в валидации. Служит для подмены зависимостей
 * в unit-тестировании.
 */
class ValidationFactory
{
    /**
     * Пространство имен классов валидаторов
     */
    const VALIDATORS_NS = '\kira\validation\validators\\';

    /**
     * Получаем экземпляр класса валидатора по названию валидатора
     *
     * Для unit-тестов подменяем любой валидатор. Всегда возвращаем TRUE, и увеличиваем проверяемое значение на единицу.
     *
     * @param string $name    название валидатора. Почти совпадает с именем класса.
     * @param mixed  $options параметры, передаваемые в класс-валидатор
     * @return validators\AbstractValidator|null
     */
    public static function makeValidator(string $name, $options)
    {
        if (Env::isUnit()) {
            return (new class extends validators\AbstractValidator
            {
                public function validate($value)
                {
                    $this->value++;
                    return true;
                }
            });
        }

        $class = self::VALIDATORS_NS . StringCase::snakeToCamel($name);
        return App::composer()->findFile($class) ? new $class($options) : null;
    }
}
