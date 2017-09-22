<?php
namespace kira\validation\validators;

use kira\exceptions\FormException;
use kira\utils\Typecast as utilsTypecast;

/**
 * Валидатор приведения значения к заданному типу
 *
 * Валидатор называется 'typecast', одно слово, не два.
 */
class Typecast extends AbstractValidator
{
    /**
     * Возможные типы для кастинга
     */
    const
        STRING = 'string',
        BOOL = 'bool',
        INT = 'int',
        FLOAT = 'float';

    /**
     * Сообщение об ошибке валидации
     * @var string
     */
    protected $error = 'Неверный тип значения';

    /**
     * Приведение к типу
     *
     * В настройках валидатора ожидаем либо строку, и тогда ее значение будет принято за требуемый тип валидаци,
     * либо настройки должны быть массивом с обязательным элементом 'type'. В любом случае заданный тип должен
     * соответствовать какой-то из констант этого класса.
     *
     * @param array $options настройки валидатора
     * @throws FormException
     */
    public function __construct($options)
    {
        if (is_string($options)) {
            $options = ['type' => $options,];
        }

        if (!isset($options['type'])) {
            throw new FormException('Не задан тип значения для кастинга');
        }

        parent::__construct($options);
    }

    /**
     * Валидатор приведения значения к заданному типу
     * @param mixed $value проверяемое значение
     * @return bool
     * @throws FormException
     */
    public function validate($value)
    {
        $type = $this->options['type'];
        if (!method_exists(utilsTypecast::class, $type)) {
            throw new FormException('Не найден метод для приведения к типу: ' . $type);
        }

        $value = utilsTypecast::{$type}($value);
        if (is_null($value)) {
            return false;
        }

        $this->value = $value;
        return true;
    }
}
