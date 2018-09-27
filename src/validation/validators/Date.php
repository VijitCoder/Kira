<?php
namespace kira\validation\validators;

/**
 * Валидатор даты
 *
 * Проверка даты по формату и ее реальности.
 *
 * Полный формат валидатора:
 * <pre>
 * [
 *    'format'  => 'Y-m-d H:i:s', // формат по умолчанию
 *    'message' => string,        // свое сообщение об ошибке
 * ]
 * </pre>
 */
class Date extends AbstractValidator
{
    /**
     * Настройки валидатора по умолчанию
     *
     * @var array
     */
    protected $options = [
        'format' => 'Y-m-d H:i:s',
    ];

    /**
     * Сообщение об ошибке валидации
     *
     * @var string
     */
    protected $error = 'Неверный формат даты';

    /**
     * Проверка даты по формату и ее реальности.
     *
     * @source http://php.net/manual/ru/function.checkdate.php#113205
     *
     * @param mixed $value проверяемое значение
     * @return bool
     */
    public function validate($value)
    {
        $this->value = $value;

        $format = $this->options['format'];
        $date = \DateTime::createFromFormat($format, $value);
        return $date && $date->format($format) == $value;
    }
}
