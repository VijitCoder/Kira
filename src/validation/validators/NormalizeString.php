<?php
namespace kira\validation\validators;

/**
 * Дезинфекция и нормализация строки
 *
 * <ul>
 * <li>удаляем начальные и конечные пробельные пробелы, а так же "\n, \r, \t, \v, \0" <b>из любого места
 * в строке</b></li>
 * <li>Удаляем лишние пробелы внутри строки</li>
 * <li>Преобразуем html-сущности, кодировка 'UTF-8'</li>
 * <li>Удаляем обратные слеши для исключения возможности написания скриптов на Perl</li>
 * </ul>
 *
 * Название валидатора может быть 'normalizeString' или 'normalize_string'.
 */
class NormalizeString extends AbstractValidator
{
    /**
     * Дезинфекция и нормализация строки. Тут нет проверок, только изменение переданного значения.
     * @param mixed $value исходная строка
     * @return bool
     */
    public function validate($value)
    {
        $value = preg_replace(['~\s{2,}~', '~\t|\n|\r|\x0B|\0|\x00~'], [' ', ''], $value);
        $value = htmlspecialchars(stripslashes($value), ENT_QUOTES, 'UTF-8');
        $value = trim($value);
        $this->value = $value;
        return true;
    }
}
