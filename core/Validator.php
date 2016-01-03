<?php
/**
 * Супер-класс. Общие валидаторы
 */

namespace core;

class Validator
{
    /**
     * Валидатор пароля
     * @param string $pass
     * @return array | string
     */
    public static function password($pass)
    {
        $errors = array();
        if(!preg_match('~^[\w!@#$%^&`\~]+$~u', $pass)) {
            $errors[] = App::t('Недопустимые символы');
        }

        if (mb_strlen($pass) < App::conf('minPass')) {
            $errors[] = App::t('Пароль слишком короткий');
        }

        //Проверка на количество наборов символов
        $cnt = 0;
        $tmp1 = preg_replace('~[^\w!@#$%^&`\~]+~u', '', $pass); //убираем левое
        $tmp2 = preg_replace('~[!@#$%^&`\~_-]+~', '', $tmp1);   //убрали спец.символы
        $cnt += $tmp2 != $tmp1 ? 1 : 0;                         //строка изменилась? Значит набор был
        $tmp1 = preg_replace('~\d+~', '', $tmp2);               //из оставшейся(!) строки убрали цифры
        $cnt += $tmp2 != $tmp1 ? 1 : 0;                         //опять изменилась? Значит цифровой набор был
        $cnt += $tmp1 != mb_strtoupper($tmp1) && $tmp1 != mb_strtolower($tmp1)
            ? 1 : 0;                                            //теперь в строке только буквы. Проверяем camelCase.
        if ($tmp1) $cnt++;                                      //факт того, что к этому моменту строка не опустела

        $minComb = App::conf('minComb');
        if ($cnt < $minComb) {
            $errors[] = App::t('Пароль слишком простой') . ", {$cnt}/{$minComb}";
        }

        return  $errors ? : $pass;
    }

    /**
     * Валидатор email
     * Проверка на корректность и черный список серверов
     * @param string $pass
     * @return array | string
     */
    public static function mail($mail)
    {
        //почему так просто? :) @link http://habrahabr.ru/post/175375/
        if (!preg_match('~.+@.+\..+~', $mail)) {
            return [App::t('неверный формат почтового адреса')];
        }

        $server = mb_substr($mail, mb_strpos($mail, '@') + 1);
        $black = App::conf('blackServers');
        if (in_array($server, $black)) {
            return [App::t('Почтовый сервер вашего email в черном списке. Пожалуйста укажите другой адрес')];
        }

        return  $mail;
    }

    /**
     * Валидатор даты. Проверяется соответствие формату и реальность даты.
     * Только тот формат, который мне нужен, dd.mm.yyyy.
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

        //приводим в формат MySQL для корректного сохранения
        return sprintf('%d-%d-%d', $y, $m, $d);
    }

    /**
     * Дезинфекция и нормализация строки
     * @param string $phrase
     * @return string
     */
    public static function normalizeString($phrase)
    {
        //удаляем начальные и конечные пробельные пробелы: "\n", "\r", "\t", "\v", "\0" и пробел.
        $phrase = trim($phrase);
        //прим.: удаляем обратные слеши для исключения возможности написания скриптов на Perl
        $phrase = htmlspecialchars(stripslashes($phrase), ENT_QUOTES, 'UTF-8');
        $phrase = preg_replace('~\s{2,}~', ' ', $phrase);
        return $phrase;
    }
}
