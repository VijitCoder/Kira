<?php
namespace kira\net;

use kira\core\App;
use kira\web\Env;

/**
 * Ответ клиенту
 *
 * См. документацию, "Обобщение"
 */
class Response
{
    /**
     * HTTP код отправляемого ответа
     *
     * @var int
     */
    private $httpCode;

    /**
     * @var array тексты кодов HTTP-статусов
     */
    private static $statuses = [
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
     * Заголовки ответа
     *
     * @var array
     */
    private $headers = [];

    /**
     * Запоминаем HTTP код отправляемого ответа
     *
     * @param int $httpCode HTTP код отправляемого ответа
     */
    public function __construct(int $httpCode = 200)
    {
        $this->httpCode = $httpCode;
    }

    /**
     * Смена HTTP кода ответа
     *
     * @param int $newCode
     * @return $this
     */
    public function changeCode(int $newCode)
    {
        $this->httpCode = $newCode;
        return $this;
    }

    /**
     * Тест HTTP-статуса по его коду
     *
     * @param $code
     * @return bool|string
     */
    public static function textOf($code)
    {
        return isset(self::$statuses[$code]) ? self::$statuses[$code] : '';
    }

    /**
     * Добавить заголовоки в ответ
     *
     * @param array|string $headers массив заголовков или один заголовок
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addHeaders($headers)
    {
        if (!$headers) {
            return $this;
        }

        if (is_array($headers)) {
            $this->headers = array_merge($this->headers, $headers);
        } elseif (is_string($headers)) {
            $this->headers[] = $headers;
        } else {
            throw new \InvalidArgumentException('Передавайте заголовки в массиве либо в строке');
        }

        return $this;
    }

    /**
     * Устанавливаем HTTP-код ответа и отправляем заголовки, собранные с этом экземпляре класса.
     *
     * Если заголовки уже были отправлены откуда-то еще, кидаем исключение. В идеале вся мета-информация ответа должна
     * идти только отсюда.
     *
     * @throws \LogicException
     */
    public function sendHeaders(): void
    {
        if (!$this->headers) {
            return;
        }

        if (headers_sent($script, $line)) {
            throw new \LogicException(
                "Не могу отправить заголовки, уже идет передача ответа. Началась тут $script:$line"
            );
        }

        http_response_code($this->httpCode);
        array_map('header', $this->headers);
    }

    /**
     * Отправляем ответ браузеру с заданными ранее заголовками
     *
     * @param string $message ответ браузеру
     */
    public function send(string $message = '')
    {
        $this->sendHeaders();
        echo $message;
    }

    /**
     * Отвечаем браузеру json-строкой с соответствующим заголовком. Кодировку объявляем UTF-8.
     *
     * Кроме отправки заголовка, остальное - обертка json_encode(). В режиме отладки добавляем JSON_PRETTY_PRINT
     * для удобства.
     *
     * Возвращаем результат прямо в output. Не завершаем выполнение программы, об этом должен заботиться клиентский код.
     *
     * @see http://php.net/manual/ru/function.json-encode.php PHP::json_encode()
     *
     * @param mixed $data    данные для упаковки в json-строку
     * @param int   $options параметры упаковки
     * @param int   $depth   максимальная глубина вложения
     */
    public function sendAsJson($data, $options = JSON_UNESCAPED_UNICODE, $depth = 512)
    {
        if (KIRA_DEBUG) {
            $options = $options | JSON_PRETTY_PRINT;
        }

        $this->addHeaders('Content-Type: application/json; charset=UTF-8')
            ->send(json_encode($data, $options, $depth));
    }

    /**
     * Отдать файл на скачивание
     *
     * Современные браузеры распознают многие типы файлов и не скачивают их, а открывают. Функция создана для
     * принудительного скачивания. Поэтому по любому файлу сообщаем "Content-type: application/octet-stream".
     *
     * Объявлять размер обязательно! Иначе отправляется Transfer-Encoding: chunked, что приводит к неполной загрузке
     * файла, если в нем обнаруживается CRLF.
     *
     * Рекомедуется завершить приложение после вызова этой функции. Или, как минимум, ничего больше не передавать
     * в output.
     *
     * @param string $file полный путь + файл
     */
    public function download(string $file): void
    {
        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        $this->addHeaders([
            'Content-Description: File Transfer',
            'Content-Type: application/octet-stream',
            'Content-Disposition: attachment; filename="' . basename($file) . '"',
            'Content-Length: ' . filesize($file),
            // антикеш
            'Expires: 0',
            'Cache-Control: must-revalidate',
            'Pragma: public',
        ])->sendHeaders();

        readfile($file);
    }

    /**
     * Редирект с выходом из приложения
     *
     * Хитрый редирект создан на основе комментария {@link http://php.net/manual/ru/function.headers-sent.php#60450}
     *
     * @param string $url  новый относительный адрес, с ведущим слешем
     * @param int    $code код ответа HTTP
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

        App::end();
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
}
