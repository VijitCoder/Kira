<?php
namespace kira\validation\validators;

use kira\exceptions\FormException;

/**
 * Валидатор, использующий функцию php:filter_var()
 *
 * По заданным настройкам валидатора вызывает php::filter_var(). Настройки:
 * <pre>
 * $options = [
 *     'filter'   => mixed,  // по правилам filter_var(). Обязательный элемент.
 *     'options'  => array,  // опции по правилам filter_var(). По ситуации.
 *     'flags'    => array,  // флаги по правилам filter_var(). По ситуации.
 *     'message'  => string, // свое сообщение об ошибке. Необязательно.
 * ];
 * </pre>
 *
 */
class FilterVar extends AbstractValidator
{
    /**
     * Сообщение об ошибке валидации
     * @var string
     */
    protected $error = 'Ошибка валидации поля';

    /**
     * Проверяем необходимые настройки валидатора
     * @param array $options настройки валидатора
     * @throws FormException
     */
    public function __construct($options)
    {
        if (!isset($options['filter'])) {
            throw new FormException('Не задан обязательный параметр "filter"');
        }

        parent::__construct($options);
    }

    /**
     * Валидатор, использующий функцию php:filter_var()
     * @param mixed $value проверяемое значение
     * @return bool
     */
    public function validate($value)
    {
        $validatorOptions = $this->options;

        $filter = $validatorOptions['filter'];
        $options = $validatorOptions['options'] ?? null;
        $flags = $validatorOptions['flags'] ?? null;

        $result = filter_var($value, $filter, compact('options', 'flags'));

        if ($result !== false) {
            $this->value = $result;
            return true;
        }

        return false;
    }
}
