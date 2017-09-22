<?php
namespace kira\utils;

use kira\core\App;

/**
 * Общие валидаторы. Используются с моделями форм {@see kira\web\Form}
 *
 * См. документацию, "Модель формы"
 *
 * От варианта вызова зависят параметры метода и возвращаемое значение. Если валидатор расчитан на вызов через
 * filter_var(FILTER_CALLBACK), то он принимает только один параметр - проверяемое значение. Возвращает либо проверенное
 * значение либо FALSE.
 *
 * Если валидатор расчитан на вызов через call_user_func(), тогда первый парамер - проверяемое значение, второй -
 * массив дополнительных параметров метода (необязательный). Такой метод всегда возвращает либо ['error' => ...], либо
 * ['value' => ...].
 */
class Validators
{
    /**
     * Валидатор даты
     *
     * Проверяется соответствие формату и реальность даты. Решил не мудрить с настройками. Валидатор разрешает только
     * формат "dd.mm.yyyy"
     *
     * Возвращаемое значение приводится в формат MySQL "yyyy-mm-dd" для корректного сохранения в БД.
     *
     * @param string $date
     * @return array возвращаем в формате yyyy-mm-dd (mysql)
     */
    public static function date($date)
    {
        if (!preg_match('~\d{2}\.\d{2}\.\d{4}~', $date)) {
            $msg = App::t('Неверный формат даты. Ожидается "FORMAT".', ['FORMAT' => 'dd.mm.yyyy']);
            return ['error' => $msg];
        }

        list($d, $m, $y) = explode('.', $date);
        if (!checkdate($m, $d, $y)) {
            return ['error' => App::t('Нереальная дата')];
        }

        return ['value' => sprintf('%d-%d-%d', $y, $m, $d)];
    }

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
     * @param string $phrase
     * @return string вызов валидатора предполагается по правилам filter_var(FILTER_CALLBACK). Результат соответствующий
     */
    public static function normalizeString($phrase)
    {
        $phrase = preg_replace(['~\s{2,}~', '~\t|\n|\r|\x0B|\0|\x00~'], [' ', ''], $phrase);
        $phrase = htmlspecialchars(stripslashes($phrase), ENT_QUOTES, 'UTF-8');
        $phrase = trim($phrase);
        return $phrase;
    }
}
