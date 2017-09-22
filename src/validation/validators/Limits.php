<?php
namespace kira\validation\validators;

use kira\core\App;
use kira\exceptions\FormException;

/**
 * Проверка значения на соблюдение заданных пределов. Для числел - диапазон значений, для строк - длина строки.
 *
 * Настройки валидатора:
 * <pre>
 * $options = [
 *     'min'     => number,
 *     'max'     => number,
 *     'message' => string|array [min => string, max => string],
 * ];
 * </pre>
 *
 * Любой из параметров можно пропустить. Значение 'NULL' - не проверять границу с этой стороны.
 */
class Limits extends AbstractValidator
{
    /**
     * Тип валидируемого значения. Приватные константы класса.
     * @internal
     */
    const
        TYPE_NUMBER = 'number',
        TYPE_STRING = 'string';

    /**
     * Дефолтные сообщения об ошибках. Библиотека
     * @var array
     */
    private $messageLibrary = [
        self::TYPE_NUMBER => [
            'min' => 'Значение меньше допустимого, минимум :min',
            'max' => 'Значение больше допустимого, максимум :max',
        ],
        self::TYPE_STRING => [
            'min' => 'Слишком короткое значение, минимум :min символов',
            'max' => 'Слишком длинное значение, максимум :max символов',
        ],
    ];

    /**
     * Нормализация настроек валидатора. Полностью переопределяем родительский конструктор.
     *
     * Если настройки заданы не массивом, очевидно нечего проверять. Ставим заглушку и выходим из метода.
     *
     * Приведение к FLOAT шире, чем нужно для длины строки, там достаточно INT. Но это не нарушит логику проверки,
     * зато позволяет обобщить код.
     *
     * @param mixed $options настройки валидатора
     * @throws FormException
     */
    public function __construct($options)
    {
        if (!is_array($options)) {
            throw new FormException('Неправильно описаны настройки валидатора. Ожидается только массив.');
        }

        $options['min'] = isset($options['min']) ? (float)$options['min'] : null;
        $options['max'] = isset($options['max']) ? (float)$options['max'] : null;

        $this->options = $options;

        $this->prepareMessages();
    }

    /**
     * Подготовка библиотеки сообщений об ошибках
     *
     * Если клиент задал свое сообщение(я), тогда заменяем дефолтные на его вариант. При этом ожидаем либо массив двух
     * сообщений [min, max] либо одно и тоже сообщение ставим на обе ошибки.
     *
     * Замена подстановок min/max на значения, заданные в настройках валидатора.
     */
    private function prepareMessages()
    {
        $minSubstitute = $this->options['min'] ?? 'NULL';
        $maxSubstitute = $this->options['max'] ?? 'NULL';

        $customMessage = $this->options['message'] ?? null;

        foreach ($this->messageLibrary as &$set) {
            if (!is_null($customMessage)) {
                $set = is_array($customMessage)
                    ? array_merge($set, $customMessage)
                    : [
                        'min' => $customMessage,
                        'max' => $customMessage,
                    ];
            }
            $set = [
                'min' => App::t($set['min'], [':min' => $minSubstitute]),
                'max' => App::t($set['max'], [':max' => $maxSubstitute]),
            ];
        }
    }

    /**
     * Валидатор проверки длины строки или диапазона числа
     * @param mixed $value проверяемое значение
     * @return bool
     * @throws FormException
     */
    public function validate($value)
    {
        $this->value = $value;

        if (is_string($value)) {
            $value = mb_strlen(strval($value));
            $messageSet = $this->messageLibrary[self::TYPE_STRING];
        } else if (is_numeric($value)) {
            $value *= 1; // приведение к типу
            $messageSet = $this->messageLibrary[self::TYPE_NUMBER];
        } else {
            throw new FormException('Проверка границ не применима к текущему типу данных: ' . gettype($value));
        }

        $min = $this->options['min'];
        $max = $this->options['max'];

        $passed = true;
        if (!is_null($min) && $value < $min) {
            $passed = false;
            $this->error = $messageSet['min'];
        } else if (!is_null($max) && $value > $max) {
            $passed = false;
            $this->error = $messageSet['max'];
        }

        return $passed;
    }
}
