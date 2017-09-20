<?php
namespace kira\validation\validators;

use kira\core\App;
use kira\exceptions\FormException;

/**
 * Проверка рационального числа в заданных пределах. Полезно для проверки чисел с плавающей точкой, в дополнение
 * к filter_var(FILTER_VALIDATE_FLOAT).
 *
 * Настройки валидатора:
 * <pre>
 * $desc = [
 *     'min'     => number,
 *     'max'     => number,
 *     'message' => string, // свое сообщение об ошибке. Необязательно.
 * ];
 * </pre>
 *
 * Любой из параметров можно пропустить. Значение 'NULL' - не проверять границу с этой стороны.
 */
class Bounds extends AbstractValidator
{
    public function __construct($options = [])
    {
        $options['min'] = isset($options['min']) ? (float)$options['min'] : null;
        $options['max'] = isset($options['max']) ? (float)$options['max'] : null;
        parent::__construct($options);
    }

    /**
     * Проверка рационального числа в заданных пределах
     * @param mixed $value проверяемое значение
     * @return bool
     * @throws FormException
     */
    public function validate($value)
    {
        if (!is_numeric($value)) {
            throw new FormException('Проверка границ числа не применима к такому типу данных: ' . gettype($value));
        }

        $min = $this->options['min'];
        $max = $this->options['max'];
        $noCustomMessage = !(bool)$this->options['message'];
        $value *= 1; // приведение к типу

        $passed = true;
        if (!is_null($min) && $value < $min) {
            $passed = false;
            if ($noCustomMessage) {
                $this->options['message'] = App::t('Значение меньше допустимого, минимум :M', [':M' => $min]);
            }
        } else if (!is_null($max) && $value > $max) {
            $passed = false;
            if ($noCustomMessage) {
                $this->options['message'] = App::t('Значение больше допустимого, максимум :M', [':M' => $max]);
            }
        }

        return $passed;
    }
}
