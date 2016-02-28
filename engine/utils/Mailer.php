<?php
/**
 * Простой скрипт по отправке почты.
 *
 * Обертки для отправки писем через mail(). Предполагается, что любые тестовые данные тут в кодировке utf-8.
 *
 * Есть несколько RFС по стандарту электронных писем. Однако не все почтовые клиенты им следуют, да и не все сервера.
 * Тема отправки писем имеет множество ньюансов. Если методы этого класса не работают в вашем случае - создавайте свои :)
 *
 * Полезно почитать
 * - php::mail() {@link http://php.net/manual/ru/function.mail.php}
 * - Как грамотно отправлять почту из скриптов {@link https://habrahabr.ru/post/17531/}
 *
 * Внимание! У себя на локалке обнаружил баг, см. коммент в функции _prepare(). Отключил кодирование заголовков во
 * избежание повтора на серверах. На боевом стоит проверить, если бага не будет, оставить функцию включенной.
 */

namespace engine\utils;

class Mailer
{
    //Файлы в письме могут быть простыми вложениями или картинками для html-версии письма.
    const ATTACH = 'attach';
    const RELATED = 'related';

    /**
     * Отправка простого текстового письма. Например, уведомление админу.
     *
     * @param string $from    от кого
     * @param string $to      получатель. Можно несколько получателей, через запятую
     * @param string $subject тема
     * @param string $text    текст
     * @return bool
     */
    public static function simple($from, $to, $subject, &$text)
    {
        $d = self::_encodeHeaders(compact('from', 'to', 'subject'));

        $headers = self::_commonHeaders($d['from']);
        $headers .= "Content-type: text/plain; charset=utf-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";

        $text = self::_encodeAndChunk($text);

        return mail($d['to'], $d['subject'], $text, $headers);
    }

    /**
     * Сложное письмо с вложениями.
     *
     * <b>Версии текстов письма</b>
     *
     * Возможно указать несколько версий текста. Де-факто пишут text и html версии. Порядок включения текстов в письмо
     * соответствует порядку их указания в массиве.
     *
     * Пример описания версий письма:
     * <pre>
     * [
     *    'text' => ...,
     *    'html' => ...,
     *    'text/html; charset=win-1251' => ...  //тоже html, но в другой кодировке
     * ]
     * </pre>
     *
     * Для ключей text|html будут установлены нужные заголовки Content-Type. Любые другие ключи пропишутся
     * в Content-Type как есть. Такие "другие" версии - эксперимент, не гарантирую их корректную работу со связанным
     * контентом из вложений.
     *
     * Если тело письма не является массивом, по умолчанию считаем его html-версией.
     *
     * <b>Вложения</b>
     *
     * Можно вложить файлы. Можно добавить картинки в html-версию и так же их приложить к письму. Пример вложений:
     * <pre>
     * [
     *    0 => '/path/file.zip',
     *    1 => '/another/path/file.doc',
     *    '/images/head.png' => 'image/png',
     *    '/images/logo.jpg' => 'image/jpeg',
     *    28 => 'images/just_file.jpg',
     * ]
     * </pre>
     *
     * Когда ключ числовой, файл будет описан как вложение с 'Content-Type: application/octet-stream;'.
     *
     * Если ключ строковый, тогда он указывает на картинку, значение - ее MIME-тип. Такие файлы будут вложены как данные,
     * связанные с html-текстом письма (multipart/related). Подключить их можно через src="cid:image_file" в теге [img].
     *
     * Прим: встроенные в html картинки можно не передавать, как вложения, если прописывать в теге [img] полный URL
     * к картинке-источнику. При этом почтовый клиент должен запросить их по заданному адресу.
     *
     * Прим: связи с картинками (multipart/related) оформляются только для версии письма, отмеченной "html". Как такие
     * связи будут работать с другими версиями - я не знаю, официально не поддерживается.
     *
     * @param              $from    от кого
     * @param              $to      кому
     * @param              $subject тема письма
     * @param array|string $body    текст письма. Если массив, тогда [text|html|MIME-type => тест письма].
     * @param array        $files   вложения. Смешанный массив.
     * @return bool
     */
    public static function complex($from, $to, $subject, &$body, $files = [])
    {
        $letter = '';
        $rn = "\r\n";

        self::_analyseFileArray($files);

        if (isset($files[self::ATTACH])) {
            $boundaryMixed = '---MIXED=' . uniqid(time());
            $letter .= "Content-Type: multipart/mixed;$rn boundary=\"$boundaryMixed\"$rn";
            $boundaryMixed = "$rn$rn--$boundaryMixed";
            $letter .= $boundaryMixed . $rn;
        } else {
            $boundaryMixed = null;
        }

        if (is_array($body)) {
            $boundaryAlt = '---ALT=' . uniqid(time());
            $letter .= "Content-Type: multipart/alternative;$rn boundary=\"$boundaryAlt\"$rn";
            $boundaryAlt = "$rn$rn--$boundaryAlt";
        } else {
            $boundaryAlt = null;
            $body = ['html' => $body];
        }

        foreach ($body as $marker => $text) {
            if ($boundaryAlt) {
                $letter .= $boundaryAlt . $rn;
            }

            if ($marker == 'html' && isset($files[self::RELATED])) {
                $boundaryRel = '---REL=' . uniqid(time());
                $letter .= "Content-Type: multipart/related;$rn boundary=\"$boundaryRel\"$rn";
                $boundaryRel = "$rn$rn--$boundaryRel";
                $letter .= $boundaryRel . $rn;
            } else {
                $boundaryRel = null;
            }

            $letter .= $marker == 'text'
                ? 'Content-type: text/plain; charset=utf-8' . $rn
                : ($marker == 'html'
                    ? 'Content-Type: text/html; charset=utf-8' . $rn
                    : 'Content-Type: ' . $marker . $rn
                );

            $letter .= 'Content-Transfer-Encoding: base64' . $rn;
            $letter .= $rn . self::_encodeAndChunk($text);

            if ($boundaryRel) {
                foreach ($files[self::RELATED] as $fn => $mime) {
                    $letter .= $boundaryRel . $rn;
                    self::_attachFile($letter, $fn, self::RELATED, $mime);
                }
                $letter .= "$boundaryRel--$rn";
            }
        }

        if ($boundaryAlt) {
            $letter .= "$boundaryAlt--$rn";
        }

        if ($boundaryMixed) {
            foreach ($files[self::ATTACH] as $fn) {
                $letter .= $boundaryMixed . $rn;
                self::_attachFile($letter, $fn, self::ATTACH);
            }
            $letter .= "$boundaryMixed--";
        }

        $d = self::_encodeHeaders(compact('from', 'to', 'subject'));
        $headers = self::_commonHeaders($d['from']);

        // Костыли в логику mail(). Итоговое письмо собирается из $headers + ПУСТАЯ СТРОКА + $letter. Эта строка ломает
        // стандарт, разбивая письмо не там, где нужно. Если в mail() не передать $headers, а все описать в $letter, как
        // надо, так mail() сама какие-то заголовки допишет и получается еще хуже. Писать на сокетах в обход mail() я
        // пока не готов.
        // Отрезаем первую группу заголовков в $letter и дописываем их в $headers
        $crutch = preg_split('~\r\n\r\n~', $letter, 2);
        $headers .= $crutch[0];
        $letter = $crutch[1];

        //dd($headers, $letter); exit; //DBG
        return mail($d['to'], $d['subject'], $letter, $headers);
    }

    /**
     * Разбираем массив файлов на обычные вложения и на файлы (картинки), относящиеся к html-версии письма.
     *
     * @param array $files
     * @return void
     */
    private static function _analyseFileArray(&$files)
    {
        if (!$files) {
            return;
        }

        $arr = [];
        foreach ($files as $k => $v) {
            if (is_string($k)) {
                $arr[self::RELATED][$k] = $v;
            } else {
                $arr[self::ATTACH][] = $v;
            }
        }
        $files = $arr;
    }

    /**
     * Кодируем один файл, собираем нужные заголовки. Добавляем текст в письмо.
     *
     * Чтобы почтовик (Thunderbird 38.5) увидел имя прикрепленного файла, нужно его указывать после MIME, причем
     * в той же строке.Или после attachment, и опять же в той же строке. Странно, но только так работает.
     *
     * Пытаясь угодить всем, добавил лишнего в заголовках. Нет времени и желания проводить основательные тесты.
     *
     * @param string $letter  приемник для текста письма. Передача по ссылке.
     * @param string $fn      путь + имя файла
     * @param string $purpose назначение вложения. См. константы этого класса
     * @param string $mime    MIME-тип
     * @return void
     */
    private static function _attachFile(&$letter, $fn, $purpose, $mime = 'application/octet-stream')
    {
        $rn = "\r\n";
        $fileContent = file_get_contents($fn);
        $fn = basename($fn);
        $encFn = self::_encodeHeaders($fn);

        $letter .= "Content-Type: $mime; name=\"$encFn\"$rn";
        if ($purpose == self::RELATED) {
            $letter .= "Content-ID: <$fn>$rn";
            $letter .= 'Content-Disposition: inline' . $rn;
        } else {
            $letter .= "Content-Disposition: attachment; filename=\"$encFn\"$rn";
        }
        $letter .= "Content-Transfer-Encoding: base64$rn";
        $letter .= $rn . self::_encodeAndChunk($fileContent);
    }

    /**
     * Заголовки, общие для любых писем.
     *
     * Не указываем в заголовках "To:" и "Subject:", это сама допишет функция mail().
     *
     * @param string $from от кого
     * @return string
     */
    private static function _commonHeaders($from)
    {
        $headers = [
            'From: ' . $from,
            'Reply-To: ' . $from,
            'Date: ' . date('r'),
            'X-Mailer: PHP/' . phpversion(),
        ];

        return implode("\r\n", $headers) . "\r\n";
    }

    /**
     * Подготовка строк заголовков согласно стандарта RFC 2047 Part Thr.
     *
     * Следует понимать, что получатель, отправитель и тема письма - это часть заголовков.
     *
     * Кодирование требуется для символов, не входящих в US-ASCII. Мы кодируем все, без разбора. Строку НЕ разбиваем,
     * хотя так требует стандарт. Если ее разбивать, почтовики неправильно воспринимают заголовок.
     *
     * Кодируем алгоритмом Base64. Считаем, что исходная строка в кодировке UTF-8.
     *
     * RFC 2047 - MIME Part Thr {@see http://www.faqs.org/rfcs/rfc2047.html}
     *
     * @param array|string $data ассоциативный массив заголовков ИЛИ один заголовок
     * @return void
     */
    private static function _encodeHeaders($data)
    {
        // TODO Тесты на локалке приводят к багу. В конце кодированного значения From, To и Reply-To добавляется имя
        // моей машины:
        //    From: =?utf-8?b?0K7Qt9C10YAgT25lIDx1c2VyX29uZUBtYWlsLnJ1Pg==?=@Z710
        // Причем это не PHP, т.к. дамп готовых значений ничего лишнего не показывает. Если записать одинаковое значение
        // в адрес отправителя и в тему письма, то этот хвост появляется только у адресов!
        // Эта приписочка очевидно ломает адрес получателя и письмо не доходит. Либо программа mail гонит, либо postfix.
        // Не знаю, как искать причину. Пока отключил кодирование заголовков.
        return $data;

        $result = [];
        foreach ((array)$data as $k => $v) {
            $result[$k] = '=?utf-8?b?' . base64_encode($v) . '?=';
        }

        return is_array($data) ? $result : $result[0];
    }

    /**
     * Кодирование произвольной строки и ее разбивка согласно стандарта RFC 2047 Part Thr
     *
     * Прим.: php::chunk_split() лишний перенос в конце строки добавляет.
     *
     * @param string $str
     * @return string
     */
    private static function _encodeAndChunk($str)
    {
        return chunk_split(base64_encode($str), 70);
    }
}
