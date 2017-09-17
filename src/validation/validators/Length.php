<?php
namespace kira\validation\validators;

use kira\core\App;
use kira\exceptions\FormException;

/**
 * Валидатор проверки длины строки
 */
class Length extends AbstractValidator
{
    /**
     * Валидатор проверки длины строки
     *
     * Описание:
     * <pre>
     * $desc = [
     *     'min'     => int,
     *     'max'     => int,
     *     'message' => string, // свое сообщение об ошибке. Необязательно.
     * ];
     * </pre>
     *
     * Любой из параметров можно пропустить. Значение '0' - неограниченная длина.
     *
     * Проверяемое значение может быть равно NULL, только если до этого были ошибки в других валидаторах. При этом
     * выходим без дополнительных сообщений.
     *
     * @param array  $desc  описание валидатора
     * @param string $data  проверяемые данные
     * @param mixed  $value куда писать валидное значение
     * @param mixed  $error куда писать ошибку
     * @return bool
     * @throws FormException
     */
    public function validate(&$desc, &$data, &$value, &$error)
    {
        if (!is_string($data)) {
            throw new FormException('Проверка длины строки не применима к текущему типу данных: '
                . gettype($value));
        }

        $min = isset($desc['min']) ? (int)$desc['min'] : 0;
        $max = isset($desc['max']) ? (int)$desc['max'] : 0;
        $message = isset($desc['message']) ? App::t($desc['message']) : '';

        $len = mb_strlen(strval($data));

        if ($min && $len < $min) {
            $errMsg[] = $message ?: App::t('Слишком короткое значение, минимум M символов', ['M' => $min]);
        } elseif ($max && $len > $max) {
            $errMsg[] = $message ?: App::t('Слишком длинное значение, максимум M символов', ['M' => $max]);
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
