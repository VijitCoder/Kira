<?php
/**
 * Общие валидаторы.
 *
 * Некоторые валидаторы настраиваются через конфиг приложения. В коментариях указано, какие именно настройки им нужны.
 * В конфиге должен быть массив 'validators'. В нем каждый ключ - имя валидатора. Значение - массив с настройками
 * для этого валидатора.
 *
 * Любой валидатор возвращает либо готовое значение (если подразумевалась очистка), либо массив с текстами ошибок.
 * Проверка переданного значения ведется до конца, не прерываясь на первой ошибке, поэтому массив.
 */

namespace engine\utils;

use engine\App;

class Validators
{
    /**
     * Валидатор пароля.
     *
     * Валидатору нужны следующие значения в конфиге (сразу с примерами):
     * 'validators'   => [
     *     'password' => [
     *       'minLen' => 5,  // минимальная длина пароля. 0 = любая длина подходит.
     *       'minComb' => 3, // минимальная комбинация наборов символов в пароле
     * ]
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
     * @param string $pass
     * @return array | string
     */
    public static function password($pass)
    {
        $errors = array();
        if (!preg_match('~^[\w!@#$%^&`\~]+$~u', $pass)) {
            $errors[] = App::t('Недопустимые символы');
        }

        if (mb_strlen($pass) < App::conf('validators.password.minLen')) {
            $errors[] = App::t('Пароль слишком короткий');
        }

        if ($minComb = App::conf('validators.password.minComb')) {
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

            if ($cnt < $minComb) {
                $errors[] = App::t('Пароль слишком простой') . ", {$cnt}/{$minComb}";
            }
        }

        return $errors ?: $pass;
    }

    /**
     * Валидатор email.
     *
     * Проверка на корректность и черный список серверов.
     *
     * Валидатору нужны следующие значения в конфиге (необязательно):
     * 'validators'   => [
     *     'mail' => [
     *       'regexp' => '~.+@.+\..+~',
     *       'blackServers' => ['example1.com', 'example2.com',]
     * ]
     *
     * Проверка на корректность. Если настройка не задана, проверяем по своей регулярке (см. в примере - она самая).
     * Почему такая простая регулярка? :) {@link http://habrahabr.ru/post/175375/}
     *
     * Проверка на черный список серверов необязательна. Нет списка - нет проверки.
     *
     * @param string $mail
     * @return array | string
     */
    public static function mail($mail)
    {
        $regexp = App::conf('validators.mail.regexp', false) ? : '~.+@.+\..+~';

        if (!preg_match($regexp, $mail)) {
            return [App::t('неверный формат почтового адреса')];
        }

        $server = mb_substr($mail, mb_strpos($mail, '@') + 1);
        $black = App::conf('validators.mail.blackServers', false) ? : [];
        if (in_array($server, $black)) {
            return [App::t('Почтовый сервер вашего email в черном списке. Пожалуйста укажите другой адрес')];
        }

        return $mail;
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
     * @return array | string возвращаем в формате yyyy-mm-dd (mysql)
     */
    public static function date($date)
    {
        if (!preg_match('~\d{2}\.\d{2}\.\d{4}~', $date)) {
            $msg = App::t('Неверный формат даты. Ожидается "FORMAT"', ['FORMAT' => 'dd.mm.yyyy']);
            return [$msg];
        }

        list($d, $m, $y) = explode('.', $date);
        if (!checkdate($m, $d, $y)) {
            return [App::t('Нереальная дата')];
        }

        return sprintf('%d-%d-%d', $y, $m, $d);
    }

    /**
     * Дезинфекция и нормализация строки.
     *
     * Удаляем начальные и конечные пробельные пробелы, а так же "\n, \r, \t, \v, \0". Удаляем лишние пробелы внутри
     * строки. Преобразуем html-сущности, кодировка 'UTF-8'. Удаляем обратные слеши для исключения возможности написания
     * скриптов на Perl.
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
