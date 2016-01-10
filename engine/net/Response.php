<?php
/**
 * Ответ клиенту.
 */

namespace engine\net;

use engine\Env;

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
     * Прим.: указание абсолютного URI - требование спецификации HTTP/1.1, {@see http://php.net/manual/ru/function.header.php}
     * Быстрая справка по кодам с редиректом {@see http://php.net/manual/ru/function.header.php#78470}
     *
     * @param string $url  новый относительный адрес, с ведущим слешем
     * @param int    $code код ответа HTTP
     * @return void
     */
    public static function redirect($url, $code = 302)
    {
        $url = Env::domainUrl() . $url;
        header('location:' . $url, true, $code);
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
     * Рекомедуется завершить приложение после вызова этой функции. Или, как минимум, ничего больше не передавать в output.
     *
     * @param string $file полный путь + файл
     * @return void
     */
    public static function download($file)
    {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        //Обязательно объявлять размер! иначе отправялется Transfer-Encoding: chunked, что приводит к неполной загрузке
        //файла, если в нем обнаруживается CRLF.
        header('Content-Length: ' . filesize($file));

        //антикеш
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        readfile($file);
    }

    /**
     * Установка печеньки с параметрами, принятыми по умолчанию. Теоретически сократит количество копипасты.
     * ttl - год, 60*60*24*365.
     * Доступ - весь сайт, включая поддомены.
     * Безопасную передачу отключить
     * Отдавать только по http-протоколу
     *
     * @param string $name  имя печеньки
     * @param string $value значение в печеньку
     */
    public static function setDefaultCookie($name, $value)
    {
        setcookie($name, $value, time() + 31536000, '/', '.' . Env::domainName(), false, true);
    }
}
