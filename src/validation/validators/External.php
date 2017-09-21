<?php
namespace kira\validation\validators;

use kira\exceptions\FormException;

/**
 * Валидатор, использующий внешнюю пользовательскую функцию для валидации значения
 *
 * По заданным настройкам вызывает внешний валидатор через php::call_user_func(). Настройки валидатора:
 * <pre>
 * $options = [
 *     'function' => mixed,  // по правилам call_user_func(). Обязательный элемент.
 *     'options'  => array,  // доп.параметры для передачи во внешний валидатор. Необязательно.
 *     'message'  => string, // свое сообщение об ошибке. Необязательно.
 * ];
 * </pre>
 *
 * Требования к внешнему валидатору:
 * <pre>
 * function someValidator(mixed $value, [array $options]) : ['error' => mixed] | ['value' => mixed]
 * </pre>
 */
class External extends AbstractValidator
{
    /**
     * Проверяем необходимые настройки валидатора
     * @param array $options настройки валидатора
     * @throws FormException
     */
    public function __construct($options = [])
    {
        if (!isset($options['function'])) {
            throw new FormException('Не задан обязательный параметр "function"');
        }

        parent::__construct($options);
    }

    /**
     * Валидатор, использующий внешнюю пользовательскую функцию для валидации значения
     * @param mixed $value проверяемое значение
     * @return bool
     * @throws FormException
     */
    public function validate($value)
    {
        $validatorOptions = $this->options;

        $function = $validatorOptions['function'];
        $options = $validatorOptions['options'] ?? [];

        $result = call_user_func($function, $value, $options);

        if (isset($result['error'])) {
            $passed = false;
            if (!$this->error) {
                $this->error = $result['error'];
            }
        } else if (isset($result['value'])) {
            $passed = true;
            $this->value = $result['value'];
        } else {
            if (is_array($function)) {
                $function = implode('::', $function);
            }
            throw new FormException("Внешний валидатор {$function}() вернул неопознанный ответ");
        }

        return $passed;
    }
}
