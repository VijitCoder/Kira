<?php
namespace kira\validation\validators;

use kira\core\App;
use kira\exceptions\FormException;

/**
 * Валидатор проверки длины строки
 *
 * Настройки валидатора:
 * <pre>
 * $options = [
 *     'min'     => int,
 *     'max'     => int,
 *     'message' => string, // свое сообщение об ошибке. Необязательно.
 * ];
 * </pre>
 *
 * Любой из параметров можно пропустить. Значение '0' - неограниченная длина.
 */
class Length extends AbstractValidator
{
    public function __construct($options = [])
    {
        $options['min'] = isset($options['min']) ? (int)$options['min'] : 0;
        $options['max'] = isset($options['max']) ? (int)$options['max'] : 0;
        parent::__construct($options);
    }

    /**
     * Валидатор проверки длины строки
     * @param mixed $value проверяемое значение
     * @return bool
     * @throws FormException
     */
    public function validate($value)
    {
        if (!is_string($value)) {
            throw new FormException('Проверка длины строки не применима к текущему типу данных: '
                . gettype($value));
        }

        $min = $this->options['min'];
        $max = $this->options['max'];
        $noCustomMessage = !(bool)$this->options['message'];

        $len = mb_strlen(strval($value));

        $passed = true;
        if ($min && $len < $min) {
            $passed = false;
            if ($noCustomMessage) {
                $this->options['message'] = App::t('Слишком короткое значение, минимум :M символов', [':M' => $min]);
            }
        } elseif ($max && $len > $max) {
            $passed = false;
            if ($noCustomMessage) {
                $this->options['message'] = App::t('Слишком длинное значение, максимум :M символов', [':M' => $min]);
            }
        }

        return $passed;
    }
}
