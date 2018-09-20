<?php
namespace kira\validation\validators;

/**
 * Дезинфекция и нормализация строки
 *
 * <ul>
 * <li>удаляем начальные и конечные пробельные пробелы, а так же "\n, \r, \t, \v, \0" <b>из любого места
 * в строке</b></li>
 * <li>удаляем лишние пробелы внутри строки</li>
 * <li>преобразуем html-сущности, кодировка 'UTF-8' ИЛИ вообще вырезаем теги (опционально)</li>
 * <li>удаляем обратные слеши для исключения возможности написания скриптов на Perl</li>
 * </ul>
 *
 * Настройки:
 * <ul>
 * <li>keep_line_breaks - сохранять переносы строк в тексте. По умолчанию - FALSE.</li>
 * <li>strip_tags - TRUE = удалять теги, FALSE = сохранять, но преобразовывать html-сущности. По умолчанию - FALSE.</li>
 * </ul>
 *
 * Название валидатора 'normalize_string'.
 */
class NormalizeString extends AbstractValidator
{
    /**
     * Настройки валидатора по умолчанию
     *
     * @var array
     */
    protected $options = [
        'keep_line_breaks' => false,
        'strip_tags'       => false,
    ];

    /**
     * Дезинфекция и нормализация строки. Тут нет проверок, только изменение переданного значения.
     *
     * Тонкость: если требуется сохранять переносы строк, то нельзя использовать спец.символ \s для пробельных символов.
     * Поэтому заведены два разных шаблона.
     *
     * @see https://stackoverflow.com/a/25956935/5497749 Match whitespace but not newlines
     *
     * @param mixed $value исходная строка
     * @return bool
     */
    public function validate($value)
    {
        // прим: немного копипасты, но так понятнее, в чем смысл
        $ridOffPattern = $this->options['keep_line_breaks']
            ? '~[\x0B,\0,\x00]+~u'
            : '~[\x0B,\0,\x00,\v]+~u';

        $value = preg_replace(['~\h+~u', $ridOffPattern], [' ', ''], $value);
        $value = stripslashes($value);
        if ($this->options['strip_tags']) {
            $value = strip_tags($value);
        }
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $value = trim($value);
        $this->value = $value;
        return true;
    }
}
