<?php
namespace kira\validation\validators;

use kira\utils\Typecast;

/**
 * Валидатор проверяет значение как id: значение должно быть целым положительным числом.
 *
 * Название валидатора может быть 'expectId' или 'expect_id'.
 */
class ExpectId extends AbstractValidator
{
    /**
     * Дефолтные параметры валидатора
     * @var array
     */
    protected $options = ['message' => 'Неверный id, должно быть целое положительное число'];

    /**
     * Валидатор проверяет значение как id: значение должно быть целым положительным числом. Преобразованное значение
     * сохраняется в свойстве валидатора.
     * @param mixed $value проверяемое значение
     * @return bool
     */
    public function validate($value)
    {
        $value = Typecast::int($value);
        $this->value = $value;
        return !is_null($value) && $value >= 0;
    }
}
