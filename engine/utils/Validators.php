<?php

namespace engine\utils;

use engine\App;

/**
 * Общие валидаторы.
 *
 * От варианта вызова зависят параметры метода и возвращаемое значение. Если валидатор расчитан на вызвов через
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
     * Валидатор пароля.
     *
     * В пароле разрешены следующие наборы символов:
     * <ul>
     *  <li>цифры</li>
     *  <li>буквы в нижнем регистре</li>
     *  <li>буквы в верхнем регистре</li>
     *  <li>спецсимволы: _ - ! @ # $ % ^ & ` ~</li>
     * </ul>
     *
     * Буквы в верхнем/нижнем регистре считаются разными наборами при проверке минимальной комбинации символов.
     *
     * Доп. настройки валидатора могут быть (указаны значения по умолчанию):
     * <pre>
     * [
     *    'min_len'  => 0, // минимальная длина пароля. 0 = любая длина подходит.
     *    'min_comb' => 1, // минимальная комбинация наборов символов в пароле
     * ]
     * </pre>
     *
     * @param string $pass проверяемый пароль
     * @param array  $options доп.настройки валидатора
     * @return array
     */
    public static function password($pass, $options = [])
    {
        $errors = array();
        $options = array_merge(['min_len' => 1, 'min_comb' => 0], $options);

        if (!preg_match('~^[\w!@#$%^&`\~]+$~u', $pass)) {
            $errors[] = App::t('Недопустимые символы.');
        }

        if (mb_strlen($pass) < $options['min_len']) {
            $errors[] = App::t('Пароль слишком короткий.');
        }

        if ($options['min_comb']) {
            $cnt = 0;
            $tmp1 = preg_replace('~[^\w!@#$%^&`\~]+~u', '', $pass); //убираем левое
            $tmp2 = preg_replace('~[!@#$%^&`\~_-]+~', '', $tmp1);   //убрали спец.символы
            $cnt += $tmp2 != $tmp1 ? 1 : 0;                         //строка изменилась? Значит набор был
            $tmp1 = preg_replace('~\d+~', '', $tmp2);               //из оставшейся(!) строки убрали цифры
            $cnt += $tmp2 != $tmp1 ? 1 : 0;                         //опять изменилась? Значит цифровой набор был
                                                                    //теперь в строке только буквы. Проверяем camelCase.
            $cnt += $tmp1 != mb_strtoupper($tmp1) && $tmp1 != mb_strtolower($tmp1) ? 1 : 0;

            if ($tmp1) {
                $cnt++; //факт того, что к этому моменту строка не опустела
            }

            if ($cnt < $options['min_comb']) {
                $errors[] = App::t('Пароль слишком простой') . ", {$cnt}/{$options['min_comb']}";
            }
        }

        return $errors ? ['error' => $errors] : ['value' => $pass];
    }

    /**
     * Валидатор email.
     *
     * Проверка на корректность и черный список серверов.
     *
     * Доп. настройки валидатора могут быть:
     * <pre>
     * [
     *   'regexp' => '~.+@.+\..+~', // значение по умолчанию
     *   'black_servers' => array | NULL
     * ]
     * </pre>
     *
     * Проверка на корректность. Если настройка не задана, проверяем по своей регулярке (см. в примере - она самая).
     * Почему такая простая регулярка? :) {@link http://habrahabr.ru/post/175375/}
     *
     * Проверка на черный список серверов необязательна. Нет списка - нет проверки.
     *
     * @param string $mail
     * @param array  $options доп.настройки валидатора
     * @return array
     */
    public static function mail($mail, $options = [])
    {
        $options = array_merge(['regexp' => '~.+@.+\..+~', 'black_servers' => null], $options);

        if (!preg_match($options['regexp'], $mail)) {
            return ['error' => App::t('неверный формат почтового адреса.')];
        }

        if ($black = $options['black_servers']) {
            $server = mb_substr($mail, mb_strpos($mail, '@') + 1);
            if (in_array($server, $black)) {
                return ['error' => App::t('Почтовый сервер вашего email в черном списке. '
                    .'Пожалуйста укажите другой адрес.')];
            }
        }

        return ['value' => $mail];
    }

    /**
     * Валидатор даты.
     *
     * Проверяется соответствие формату и реальность даты.  Решил не мудрить в настройками. Валидатор разрешает только
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
     * Дезинфекция и нормализация строки.
     *
     * Удаляем начальные и конечные пробельные пробелы, а так же "\n, \r, \t, \v, \0". Удаляем лишние пробелы внутри
     * строки. Преобразуем html-сущности, кодировка 'UTF-8'. Удаляем обратные слеши для исключения возможности написания
     * скриптов на Perl.
     *
     * Прим: вызов валидатора предполагается по правилам filter_var(FILTER_CALLBACK). Результат соответствующий.
     *
     * @param string $phrase
     * @return string
     */
    public static function normalizeString($phrase)
    {
        $phrase = trim($phrase);
        $phrase = htmlspecialchars(stripslashes($phrase), ENT_QUOTES, 'UTF-8');
        $phrase = preg_replace('~\s{2,}~', ' ', $phrase);
        return $phrase;
    }
}
