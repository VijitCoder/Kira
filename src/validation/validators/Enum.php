<?php
namespace kira\validation\validators;

use kira\exceptions\FormException;
use kira\utils\Arrays;

/**
 * Валидатор проверяет значение по списку допустимых значений
 *
 * Можно указать набор значений в неассоциативном массиве или просто в строке с разделителем "|". Это минимальная
 * настройка валидатора.
 *
 * Полный формат валидатора:
 * <pre>
 * [
 *    values      => [val1, val2, ...] или 'val1|val2|...',
 *    insensitive => bool,   // TRUE - регистр не важен, FALSE (по умолчанию) - регистрозависимое сравнение.
 *    message     => string, // свое сообщение об ошибке
 * ]
 * </pre>
 */
class Enum extends AbstractValidator
{
    /**
     * Настройки валидатора по умолчанию
     * @var array
     */
    protected $options = [
        'values'      => [],
        'insensitive' => false,
    ];

    /**
     * Сообщение об ошибке валидации
     * @var string
     */
    protected $error = 'Значение не найдено в списке допустимых значений';

    /**
     * Требуем наличие обязательных настроек валидатора
     * @param array|true $options
     * @throws FormException
     */
    public function __construct($options)
    {
        if (!$options
            || (is_array($options) && Arrays::isAssociative($options) && !isset($options['values']))
        ) {
            throw new FormException('Не задан набор допустимых значений');
        }

        if (!isset($options['values'])) {
            $options = ['values' => $options];
        }

        parent::__construct($options);

        $this->prepareValues();
    }

    /**
     * Приводим список допустимых значений к формату для внутреннего использования в валидаторе
     */
    private function prepareValues(): void
    {
        $values = $this->options['values'];

        if (!is_array($values)) {
            $values = explode('|', $values);
        }

        if ($this->options['insensitive']) {
            $values = array_map('mb_strtolower', $values);
        }

        $this->options['values']  = $values;
    }

    /**
     * Проверяем значение по списку допустимых значений
     *
     * Если требуется регистронезависимое сравнение, приводим значение к нижнему регистру.
     * Список допустимых значений уже приведен к нижнему регистру.
     *
     * @param mixed $value проверяемое значение
     * @return bool
     */
    public function validate($value)
    {
        $this->value = $value;

        if ($this->options['insensitive']) {
            $value = mb_strtolower($value);
        }

        return in_array($value, $this->options['values'], true);
    }
}
