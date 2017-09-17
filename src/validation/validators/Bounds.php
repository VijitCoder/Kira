<?php
namespace kira\validation\validators;

use kira\core\App;
use kira\exceptions\FormException;

/**
 * Проверка рационального числа в заданных пределах. Полезно для проверки чисел с плавающей точкой, в дополнение
 * к filter_var(FILTER_VALIDATE_FLOAT).
 */
class Bounds extends AbstractValidator
{

    /**
     * Проверка рационального числа в заданных пределах
     *
     * Описание:
     * <pre>
     * $desc = [
     *     'min'     => number,
     *     'max'     => number,
     *     'message' => string, // свое сообщение об ошибке. Необязательно.
     * ];
     * </pre>
     *
     * Любой из параметров можно пропустить. Значение 'NULL' - не проверять границу с этой стороны.
     *
     * Проверяемое значение может быть равно NULL, только если до этого были ошибки в других валидаторах. При этом
     * выходим без дополнительных сообщений.
     *
     * @param array  $desc  описание валидатора
     * @param number $data  проверяемые данные
     * @param mixed  $value куда писать валидное значение
     * @param mixed  $error куда писать ошибку
     * @return bool
     * @throws FormException
     */
    public function validate(&$desc, &$data, &$value, &$error)
    {
        if (is_null($data)) {
            $this->isValid = false;
            return false;
        }

        if (!is_numeric($data)) {
            throw new FormException('Проверка границ числа не применима к такому типу данных: ' . gettype($value));
        }

        $min = isset($desc['min']) ? (float)$desc['min'] : null;
        $max = isset($desc['max']) ? (float)$desc['max'] : null;
        $message = isset($desc['message']) ? App::t($desc['message']) : '';
        $data *= 1; // приведение к типу

        if (!is_null($min) && $data < $min) {
            $errMsg[] = $message ?: App::t('Значение меньше допустимого, минимум M', ['M' => $min]);
        } elseif (!is_null($min) && $data > $max) {
            $errMsg[] = $message ?: App::t('Значение больше допустимого, максимум M', ['M' => $max]);
        } else {
            $errMsg = null;
        }

        if (!($passed = !$errMsg)) {
            $this->isValid = false;
            $error = is_array($error) ? array_merge($error, $errMsg) : $errMsg;
        }

        return $passed;
    }
}
