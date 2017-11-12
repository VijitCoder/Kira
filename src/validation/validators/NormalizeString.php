<?php
namespace kira\validation\validators;

/**
 * Дезинфекция и нормализация строки
 *
 * <ul>
 * <li>удаляем начальные и конечные пробельные пробелы, а так же "\n, \r, \t, \v, \0" <b>из любого места
 * в строке</b></li>
 * <li>удаляем лишние пробелы внутри строки</li>
 * <li>преобразуем html-сущности, кодировка 'UTF-8'</li>
 * <li>удаляем обратные слеши для исключения возможности написания скриптов на Perl</li>
 * </ul>
 *
 * Настройка keep_line_breaks = TRUE позволит сохранять переносы строк в тексте.
 *
 * Название валидатора 'normalize_string'.
 */
class NormalizeString extends AbstractValidator
{
    /**
     * Настройки валидатора по умолчанию
     * @var array
     */
    protected $options = [
        'keep_line_breaks' => false,
    ];

    /**
     * Дезинфекция и нормализация строки. Тут нет проверок, только изменение переданного значения.
     * @param mixed $value исходная строка
     * @return bool
     */
    public function validate($value)
    {
        // прим: немного копипасты, но так понятнее, в чем смысл
        $ridOffPattern = $this->options['keep_line_breaks']
            ? '~\t|\x0B|\0|\x00~'
            : '~\t|\x0B|\0|\x00|\n|\r~';

        $value = preg_replace(['~\s{2,}~', $ridOffPattern], [' ', ''], $value);
        $value = htmlspecialchars(stripslashes($value), ENT_QUOTES, 'UTF-8');
        $value = trim($value);
        $this->value = $value;
        return true;
    }
}
