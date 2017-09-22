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
     * Не проверяем значение, как массив, т.к. этим занимается другой валидатор и он гарантирует здесь только
     * скалярное значение.
     *
     * Прим: нельзя для проверки использовать php::empty(), т.к. "0", 0 и 0.0 - это допустимые значения.
     *
     * @param mixed $value проверяемое значение
     * @return bool
     */
    public function validate($value)
    {
        $this->value = $value;
        return !is_null($value) && preg_match('/.{1,}/', $value);
    }
}
