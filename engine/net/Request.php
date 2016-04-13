<?php
/**
 * Запрос клиента.
 *
 * Обертки для получения значений из суперглобальных массивов GET|POST|COOKIE|REQUEST; получение полного адреса запроса,
 * ip юзера и т.п.
 *
 * Прим.: в PECL есть расширение с похожим назначением {@see http://php.net/manual/en/class.httprequest.php}.
 * Но допустим, нам так много ненужно :)
 */

namespace engine\net;

use engine\Env;

/**
 * Class Request
 *
 * Серия методов для работы с суперглобальными переменными $_GET, $_POST, $_COOKIE и $_REQUEST. Логика у них одинаковая,
 * реализация оформлена в магическом методе. Удобство этих методов том, что в клиентском коде не придется проверять
 * значение из массива на существование и/или приведение к типу.
 *
 * Получение одного значения из массива GET|POST|REQUEST. Если ключ в массиве не существует, вернем NULL:
 *
 * @method string|null get(string $key)
 * @method string|null post(string $key)
 * @method string|null cookie(string $key)
 * @method string|null request(string $key)
 *
 * Прим: если ключ не указан, возвращаем весь массив, что ничем не отличается от прямого обращения к суперглобальной
 * переменной. Такой вызов поддерживаются для полноты картины.
 *
 * Значение из массива, приведенное к числу:
 *
 * @method static int|null getAsInt(string $key)
 * @method static int|null postAsInt(string $key)
 * @method static int|null cookieAsInt(string $key)
 * @method static int|null requestAsInt(string $key)
 *
 * Значение из массива, приведенное к булевому типу:
 *
 * @method static bool|null getAsBool(string $key)
 * @method static bool|null postAsBool(string $key)
 * @method static bool|null cookieAsBool(string $key)
 * @method static bool|null requestAsBool(string $key)
 *
 * Значение из массива, с валидацией через регулярное выражение. Если параметр не существует или не подходит
 * по регулярке, вернем NULL:
 *
 * @method static string|null getAsRegexp(string $key, string $pattern)
 * @method static string|null postAsRegexp(string $key, string $pattern)
 * @method static string|null cookieAsRegexp(string $key, string $pattern)
 * @method static string|null requestAsRegexp(string $key, string $pattern)
 *
 * Значение из массива, с проверкой с в списке допустимых значений. Если параметр не существует или его нет в списке
 * вернем NULL:
 *
 * @method static string|null getAsEnum(string $key, string $expect)
 * @method static string|null postAsEnum(string $key, string $expect)
 * @method static string|null cookieAsEnum(string $key, string $expect)
 * @method static string|null requestAsEnum(string $key, string $expect)
 */
class Request
{
    /**
     * Получение данных из суперглобальных массивов $_GET, $_POST, $_COOKIE или $_REQUEST.
     *
     * Описание методов см. в комментарии к этому классу.
     *
     * Суть: парсим запрошенный метод. Если совпадает с тем, что мы поддерживаем, подключаемся к нужному массиву.
     * По заданному количеству параметров функции пытаемся вернуть требуемое значение. В частности в методе приведения
     * к типу ожидаем имя ключа в массиве, а при регулярке еще один параметр - шаблон регулярного выражения.
     *
     * Прим: параметры в этот метод попадают в виде неассоциативного массива, приходится по порядку ключей разбирать.
     *
     * Если вызов неправильный, кидаем исключение. Если значение в массиве не найдено (или регулярке не отвечает) - NULL
     *
     * @param string $method имя метода
     * @param array $params  параметры метода
     * @return mixed
     * @throws \RuntimeException
     */
    public static function __callStatic($method, $params)
    {
        if (preg_match('/^(?<verb>get|post|request|cookie)(As(?<type>Int|Bool|Regexp|Enum))?$/', $method, $m)) {
            $verb = $m['verb'];
            $type = isset($m['type']) ? $m['type'] : null;
        } else {
            throw new \RuntimeException('Неизвестный метод ' . __NAMESPACE__ . "\\Request::$method()");
        }

        // Прим.: нельзя использовать переменные переменных в данном случае. Поэтому - через условие.
        // {@see http://php.net/manual/ru/language.variables.variable.php}, внизу варнинг.
        switch ($verb) {
            case 'get':     $arr = &$_GET; break;
            case 'post':    $arr = &$_POST; break;
            case 'cookie':  $arr = &$_COOKIE; break;
            case 'request': $arr = &$_REQUEST; break;
        }

        if (!$params) {
            if ($type) {
                $secondParam = $type == 'Regexp' ? ', $pattern' : ($type == 'Enum' ? ', $expect' : '');
                throw new \RuntimeException("Пропущен обязательный параметр функции: имя ключа в массиве \$_{$verb}"
                    . "\nСигнатрура вызова: {$method}(\$key{$secondParam})");
            }
            return $arr;
        }

        $key = &$params[0];
        $val = (isset($arr[$key])) ? $arr[$key] : null;

        if (!$type || !$val) {
            return $val;
        }

        switch ($type) {
            case 'Int':
                return intval($val);
            case 'Bool':
                return preg_match('~^true|1|on|checked|истина|да$~u', $val) ? true : false;
            case 'Regexp':
                if (!isset($params[1])) {
                    throw new \RuntimeException("Пропущен обязательный параметр функции: шаблон регулярного выражения.\n"
                        . "Сигнатрура вызова: {$verb}AsRegexp(\$key, \$pattern)");
                }
                $pattern = &$params[1];
                return preg_match($pattern, $val) ? $val : null;
            case 'Enum':
                if (!isset($params[1])) {
                    throw new \RuntimeException("Пропущен обязательный параметр функции: массив допустимых значений.\n"
                        . "Сигнатрура вызова: {$verb}AsEnum(\$key, \$expect)");
                }
                return in_array($val, $params[1]) ? $val : null;
        }
    }

    /**
     * С каким методом (http-глаголом) пришел запрос.
     *
     * RESTful методы: GET, POST, DELETE, PUT.
     * Есть еще OPTIONS, HEAD. Может еще какая-то экзотика.
     *
     * @see http://www.restapitutorial.ru/lessons/httpmethods.html
     *
     * @return string|null
     */
    public function method()
    {
        return isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;
    }

    /**
     * Абсолютный URL текущей страницы.
     *
     * Если нет заголовка 'HTTP_HOST', вернем NULL.
     *
     * @copyright  2007-2010 SARITASA LLC <info@saritasa.com>
     * @link       http://www.saritasa.com
     *
     * @return string|null
     */
    public function absoluteURL()
    {
        if (!isset($_SERVER['HTTP_HOST'])) {
            return false;
        }

        $user = isset($_SERVER['PHP_AUTH_USER'])
            ? $_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW'] . '@'
            : '';

        $url = Env::scheme() . $user . $_SERVER['HTTP_HOST'] . Env::port();

        $relatedUrl = '';

        if (isset($_SERVER['X_ORIGINAL_URL'])) {
            $relatedUrl = $_SERVER['X_ORIGINAL_URL'];
        } else if (isset($_SERVER['X_REWRITE_URL'])) {
            $relatedUrl = $_SERVER['X_REWRITE_URL'];
        } else if (isset($_SERVER['IIS_WasUrlRewritten']) && $_SERVER['IIS_WasUrlRewritten'] == '1'
            && !empty($_SERVER['UNENCODED_URL'])
        ) {
            $relatedUrl = $_SERVER['UNENCODED_URL'];
        } else if (isset($_SERVER['REQUEST_URI'])) {
            $relatedUrl = $_SERVER['REQUEST_URI'];
            if (strpos($relatedUrl, $url) === 0) {
                $relatedUrl = substr($relatedUrl, strlen($url));
            }
        } else if (isset($_SERVER['ORIG_PATH_INFO'])) {
            $relatedUrl = $_SERVER['ORIG_PATH_INFO'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $relatedUrl .= '?' . $_SERVER['QUERY_STRING'];
            }
        } else if (isset($_SERVER['PHP_SELF'])) {
            $relatedUrl = $_SERVER['PHP_SELF'];
        }

        return $url . $relatedUrl;
    }

    /**
     * Получение ip юзера.
     *
     * @link http://stackoverflow.com/questions/15699101/get-the-client-ip-address-using-php
     * @return string|null
     */
    public function userIP()
    {
        return
            getenv('HTTP_CLIENT_IP') ?:
                getenv('HTTP_X_FORWARDED_FOR') ?:
                    getenv('HTTP_X_FORWARDED') ?:
                        getenv('HTTP_FORWARDED_FOR') ?:
                            getenv('HTTP_FORWARDED') ?:
                                getenv('REMOTE_ADDR') ?:
                                    '';
    }

    /**
     * Проверяем, является ли запрос браузера ajax-запросом или подобной реализацией XMLHttpRequest.
     *
     * Следует помнить, что js-код должен явно передать нужный заголовок, хотя это не требуется стандартом
     * XMLHttpRequest. Например, JQuery.ajax это делает, а некоторые другие реализации стандарта - нет. Поэтому метод
     * назван именно "ajax".
     *
     * @return bool
     */
    public function isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
    }

    /**
     * Проверяем, что запрос пришел с мобильного браузера.
     *
     * Используемая регулярка взята здесь {@link http://detectmobilebrowsers.com}
     *
     * @return bool
     */
    public function isMobileBrowser()
    {
        return isset($_SERVER['HTTP_USER_AGENT'])
            ? (bool)(preg_match(
                    '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $_SERVER['HTTP_USER_AGENT'])
                || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',
                    substr($_SERVER['HTTP_USER_AGENT'], 0, 4))
            )
            : false;
    }
}
