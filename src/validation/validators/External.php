<?php
namespace kira\validation\validators;

use kira\core\App;
use kira\exceptions\FormException;

/**
 * Валидатор, использующий внешнюю пользовательскую функцию для валидации значения
 */
class External extends AbstractValidator
{
    /**
     * Валидатор, использующий внешнюю пользовательскую функцию для валидации значения
     *
     * По заданному описанию вызывает внешний валидатор через php::call_user_func(). Описание:
     * <pre>
     * $desc = [
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
        if (!isset($desc['function'])) {
            throw new FormException('Не задан обязательный параметр "function"');
        }
        $function = $desc['function'];
        $options = isset($desc['options']) ? ($desc['options']) : [];

        $result = call_user_func($function, $data, $options);

        if (isset($result['error'])) {
            $this->isValid = false;
            $passed = false;

            $message = isset($desc['message']) ? App::t($desc['message']) : $result['error'];
            if (!is_array($message)) {
                $message = [$message];
            }

            $error = is_array($error) ? array_merge($error, $message) : $message;
        } elseif (isset($result['value'])) {
            $passed = true;
            $value = $result['value'];
        } else {
            if (is_array($function)) {
                $function = implode('::', $function);
            }
            throw new FormException("Внешний валидатор {$function}() вернул неопознанный ответ");
        }

        return $passed;
    }
}
