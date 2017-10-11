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
     * 'strict' - строка только из пробелов ИЛИ массив только с NULL элементами рассценивать, как недопустимые.
     * @var array
     */
    protected $options = [
        'strict' => true,
    ];

    /**
     * Проверка необходимого значения на существование
     *
     * Прим: для проверки скалярного значения нельзя использовать php::empty(), т.к. "0", 0 и 0.0 - это допустимые
     * значения.
     *
     * При строгом режиме проверки строка только из пробелов ИЛИ массив только с NULL элементами рассцениваются, как
     * недопустимые.
     *
     * @param mixed $value проверяемое значение
     * @return bool
     */
    public function validate($value)
    {
        $this->value = $value;
        $strict = $this->options['strict'];

        if (is_array($value)) {
            if ($strict) {
                $value = array_filter($value);
            }
            return !empty($value);
        }

        if ($strict && trim($value) === '') {
            $value = null;
        }

        return !is_null($value) && preg_match('/.{1,}/', $value);
    }
}
