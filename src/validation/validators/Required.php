<?php
namespace kira\validation\validators;

/**
 * Проверка необходимого значения на существование
 */
class Required extends AbstractValidator
{
    /**
     * Сообщение об ошибке валидации
     * @var string
     */
    protected $error = 'Поле должно быть заполнено';

    /**
     * Проверка необходимого значения на существование
     *
     * Прим: для проверки скалярного значения нельзя использовать php::empty(), т.к. "0", 0 и 0.0 - это допустимые
     * значения.
     *
     * @param mixed $value проверяемое значение
     * @return bool
     */
    public function validate($value)
    {
        $this->value = $value;
        if (is_array($value)) {
            return !empty($value);
        }
        return !is_null($value) && preg_match('/.{1,}/', $value);
    }
}
