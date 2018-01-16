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
            || is_array($options) && Arrays::isAssociative($options) && !isset($options['values'])
        ) {
            throw new FormException('Не задан набор допустимых значений');
        }

        if (!isset($options['values'])) {
            $options = [
                'values' => $options,
            ];
        }

        parent::__construct($options);
    }

    /**
     * Валидатор проверяет значение как id: значение должно быть целым положительным числом. Преобразованное значение
     * сохраняется в свойстве валидатора.
     * @param mixed $value проверяемое значение
     * @return bool
     */
    public function validate($value)
    {
        $this->value = $value;

        $allowedValues = $this->options['values'];
        if (is_array($allowedValues)) {
            $allowedValues = implode('|', $allowedValues);
        }

        $pattern = "/^{$allowedValues}$/u";
        if ($this->options['insensitive']) {
            $pattern .= 'i';
        }

        return (bool)preg_match($pattern, $value);
    }
}
