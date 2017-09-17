<?php
namespace kira\validation\validators;

use kira\core\App;
use kira\exceptions\FormException;

/**
 * Валидатор, использующий функцию php:filter_var()
 */
class FilterVar extends AbstractValidator
{
    /**
     * Валидатор, использующий функцию php:filter_var()
     *
     * По заданному описанию вызывает php::filter_var(). Описание:
     * <pre>
     * $desc = [
     *     'filter'   => mixed,  // по правилам filter_var(). Обязательный элемент.
     *     'options'  => array,  // опции по правилам filter_var(). По ситуации.
     *     'flags'    => array,  // флаги по правилам filter_var(). По ситуации.
     *     'message'  => string, // свое сообщение об ошибке. Необязательно.
     * ];
     * </pre>
     *
     * @param array $desc  описание валидатора
     * @param mixed $data  проверяемые данные
     * @param mixed $value куда писать валидное значение
     * @param mixed $error куда писать ошибку
     * @return bool
     * @throws FormException
     */
    public function validate(&$desc, &$data, &$value, &$error)
    {
        if (!isset($desc['filter'])) {
            throw new FormException('Не задан обязательный параметр "filter"');
        }
        $filter = $desc['filter'];
        $options = $desc['options'] ?? null;
        $flags = $desc['flags'] ?? null;

        $passed = filter_var($data, $filter, compact('options', 'flags'));

        if ($passed === false) {
            $this->isValid = false;
            $message = isset($desc['message']) ? $desc['message'] : 'Ошибка валидации поля';
            $message = [App::t($message)];
            $error = is_array($error) ? array_merge($error, $message) : $message;
        } else {
            $value = $passed;
        }

        return $passed;
    }
}
