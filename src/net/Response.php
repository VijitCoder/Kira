<?php
namespace kira\net;

use kira\Env;

/**
 * Ответ клиенту.
 */
class Response
{
    /** @var array тексты кодов HTTP-статусов */
    private static $_statuses = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Checkpoint',
        122 => 'Request-URI too long',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        308 => 'Resume Incomplete',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        420 => 'Enhance Your Calm',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        444 => 'No Response',
        449 => 'Retry With',
        450 => 'Blocked by Windows Parental Controls',
        499 => 'Client Closed Request',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        509 => 'Bandwidth Limit Exceeded',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
        598 => 'Network read timeout error',
        599 => 'Network connect timeout error',
    ];

    /**
     * Тест HTTP-статуса по его коду.
     *
     * @param $code
     * @return bool|string
     */
    public static function textOf($code)
    {
        return isset(self::$_statuses[$code]) ? self::$_statuses[$code] : '';
    }

    /**
     * Редирект с выходом из приложения.
     *
     * Прим.: указание абсолютного URL - требование спецификации HTTP/1.1,
     * {@link http://php.net/manual/ru/function.header.php}
     *
     * Быстрая справка по кодам с редиректом {@link http://php.net/manual/ru/function.header.php#78470}
     *
     * Хитрый редирект на основе комментария {@link http://php.net/manual/ru/function.headers-sent.php#60450}
     *
     * @param string $url  новый относительный адрес, с ведущим слешем
     * @param int    $code код ответа HTTP
     * @return void
     */
    public static function redirect($url, $code = 302)
    {
        $url = Env::domainUrl() . $url;

        if (!headers_sent()) {
            header('location:' . $url, true, $code);
        } else {
            echo "
                <script type='text/javascript'>
                    window.location.href='$url'
                </script>
                <noscript>
                    <meta http-equiv='refresh' content='0; url=$url'/>
                </noscript>
            ";
        }

        exit;
    }

    /**
     * Отдать файл на скачивание.
     *
     * @see http://php.net/manual/ru/function.readfile.php
     * @see http://ru.wikipedia.org/wiki/Chunked_transfer_encoding
     *
     * По-хорошему нужно в Content-type передавать реальный MIME файла. Для определения использовать, например,
     * php-расширение "fileinfo". Но современные браузеры прекрасно распознают многие типы файлов и не скачивают их,
     * а открывают. Функция же создана для принудительного скачивания. Поэтому по любому файлу сообщаем
     * "Content-type: application/octet-stream".
     *
     * Обязательно объявлять размер! Иначе отправляется Transfer-Encoding: chunked, что приводит к неполной загрузке
     * файла, если в нем обнаруживается CRLF.
     *
     * Рекомедуется завершить приложение после вызова этой функции. Или, как минимум, ничего больше не передавать
     * в output.
     *
     * @param string $file полный путь + файл
     * @return void
     * @throws \LogicException
     */
    public static function download($file)
    {
        if (headers_sent($file, $line)) {
            throw new \LogicException("Не могу отправить заголовки, уже идет передача ответа. Началась тут $file:$line");
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));

        // антикеш
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        readfile($file);
    }

    /**
     * Установка печеньки с параметрами, принятыми по умолчанию. Теоретически сократит количество копипасты.
     *
     * <pre>
     * ttl - год
     * Доступ - весь сайт
     * Безопасную передачу отключить
     * Отдавать только по http-протоколу
     * </pre>
     *
     * @param string $name  имя печеньки
     * @param string $value значение в печеньку
     */
    public static function setDefaultCookie($name, $value)
    {
        setcookie($name, $value, strtotime('+1 year'), '/', '', false, true);
    }

    /**
     * Отвечаем браузеру json-строкой с соответствующим заголовком.
     *
     * Кроме отправки заголовка, остальное - обертка json_encode(), с предустановленными опциями:
     * <ul>
     * <li><b>JSON_UNESCAPED_UNICODE</b>. Работаем с UTF-8, javascript так же работает только в этой кодировке.
     * Т.о. лишнее кодирование национальных алфавитов тут действительно лишнее, зато усложняет отладку и увеличивает вес
     * ответа.</li>
     * <li> в режиме отладки добавляем <b>JSON_PRETTY_PRINT</b>, для удобства.</li>
     * </ul>
     *
     * Возвращаем результат прямо в output. Не завершаем выполнение программы, об этом должен заботиться клиентский код.
     *
     * @see http://php.net/manual/ru/function.json-encode.php PHP::json_encode()
     *
     * @param mixed $data    данные для упаковки в json-строку
     * @param int   $options параметры упаковки
     * @param int   $depth   максимальная глубина вложения
     * @return void
     */
    public static function sendAsJson($data, $options = JSON_UNESCAPED_UNICODE, $depth = 512)
    {
        header('Content-Type: application/json');
        if (DEBUG) {
            $options = $options | JSON_PRETTY_PRINT;
        }
        echo json_encode($data, $options, $depth);
    }

    /**
     * Отвечаем браузеру текстом с заголовком кодировки.
     *
     * Для ситуаций, когда нет ни каких шаблонов или других способов уточнить кодировку.
     *
     * @todo заголовки принимать в параметре функции. Только по умолчанию передавать тот заголовок, что сейчас есть.
     *
     * @param string $message
     * @return void
     */
    public static function send($message)
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo $message;
    }
}