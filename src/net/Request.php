<?php
namespace kira\net;

use kira\exceptions\RequestException;
use kira\utils\System;
use kira\utils\Typecast;
use kira\web\Env;
use kira\utils;

/**
 * Запрос клиента.
 *
 * Обертки для получения значений из суперглобальных массивов GET|POST|COOKIE|REQUEST; получение полного адреса запроса,
 * ip юзера и т.п.
 *
 * См. документацию, "Обобщение"
 *
 * Серия методов для работы с суперглобальными переменными $_GET, $_POST, $_COOKIE и $_REQUEST.
 *
 * Получение одного значения из массива GET|POST|REQUEST. Если ключ в массиве не существует, вернем NULL:
 *
 * @method static array|string|null get(string $key = null)
 * @method static array|string|null post(string $key = null)
 * @method static array|string|null cookie(string $key = null)
 * @method static array|string|null request(string $key = null)
 *
 * Ключ можно указать составной, типа "['lvl1' => ['lvl2' => 'param1']]". Если ключ не указан, возвращаем весь массив
 * супеглобальной переменной.
 *
 *
 * Значение из массива, приведенное к целому числу (ведущие нули не сохраняются):
 *
 * @method static int|null getAsInt(string $key, $default = null)
 * @method static int|null postAsInt(string $key, $default = null)
 * @method static int|null cookieAsInt(string $key, $default = null)
 * @method static int|null requestAsInt(string $key, $default = null)
 *
 * Если значение является именно целым числом, оно будет приведено к типу и возвращено. Иначе - $default.
 * Т.е. не происходит выделение числа из строки, так же содержащей нечисловые символы. Она считается невалидной.
 *
 *
 * Значение из массива, приведенное к булевому типу:
 *
 * @method static bool|null getAsBool(string $key, $default = null)
 * @method static bool|null postAsBool(string $key, $default = null)
 * @method static bool|null cookieAsBool(string $key, $default = null)
 * @method static bool|null requestAsBool(string $key, $default = null)
 *
 * Значение true|1|on|yes|checked = TRUE, false|0|off|no|unchecked = FALSE. Любой регистр. В остальных
 * случаях - $default.
 *
 *
 * Значение из массива, приведенное к строке:
 *
 * @method static string|null getAsString(string $key, $default = null)
 * @method static string|null postAsString(string $key, $default = null)
 * @method static string|null cookieAsString(string $key, $default = null)
 * @method static string|null requestAsString(string $key, $default = null)
 *
 * Значением может оказаться массив, когда ожидаем строку. Тогда возвращаем $default. Если значение строковое,
 * возвращаем его, "как есть".
 *
 *
 * Значение из массива с проверкой через регулярное выражение. Если параметр не существует или не подходит
 * по регулярке, вернем $default:
 *
 * @method static string|null getAsRegexp(string $key, string $pattern, $default = null)
 * @method static string|null postAsRegexp(string $key, string $pattern, $default = null)
 * @method static string|null cookieAsRegexp(string $key, string $pattern, $default = null)
 * @method static string|null requestAsRegexp(string $key, string $pattern, $default = null)
 *
 *
 * Значение из массива с проверкой в списке допустимых значений. Если параметр не существует или его нет в списке
 * вернем $default:
 *
 * @method static string|null getAsEnum(string $key, string $expect, $default = null)
 * @method static string|null postAsEnum(string $key, string $expect, $default = null)
 * @method static string|null cookieAsEnum(string $key, string $expect, $default = null)
 * @method static string|null requestAsEnum(string $key, string $expect, $default = null)
 */
class Request
{
    /**
     * Методы приведения к типу, ожидающие один обязательный параметр
     */
    const SINGLE_PARAM_TYPES = ['String', 'Int', 'Bool',];

    /**
     * Получение данных из суперглобальных массивов $_GET, $_POST, $_COOKIE или $_REQUEST. Описание методов
     * см. в комментарии к этому классу.
     *
     * Если вызов метода неправильный, кидаем исключение. Если значение в массиве не найдено (или регулярке
     * не отвечает), возвращаем NULL.
     *
     * Прим: параметры в этот метод попадают в виде неассоциативного массива, приходится по порядку ключей разбирать.
     *
     * @param string $method имя метода
     * @param array  $params параметры метода
     * @return mixed
     * @throws RequestException
     */
    public static function __callStatic($method, $params)
    {
        if (preg_match('/^(?<verb>get|post|request|cookie)(As(?<type>String|Int|Bool|Regexp|Enum))?$/', $method, $m)) {
            $verb = $m['verb'];
            $type = $m['type'] ?? '';
        } else {
            throw new RequestException('Неизвестный метод ' . __NAMESPACE__ . "\\Request::$method()");
        }

        if ($notEnoughParams = self::checkRequiredParamsCount($verb, $type, $params)) {
            throw new RequestException($notEnoughParams);
        }

        // Прим.: нельзя использовать переменные переменных в данном случае. Поэтому - через условие.
        // {@see http://php.net/manual/ru/language.variables.variable.php}, внизу варнинг.
        switch ($verb) {
            case 'get':
                $values = &$_GET;
                break;
            case 'post':
                $values = &$_POST;
                break;
            case 'cookie':
                $values = &$_COOKIE;
                break;
            case 'request':
                $values = &$_REQUEST;
                break;
            default:
                $values = [];
        }

        if (!$params) {
            return $values;
        }

        $default = self::getDefaultValue($type, $params);

        $key = &$params[0];
        $value = utils\Arrays::getValue($values, $key);

        if (!($type && $value)) {
            return $value ?? $default;
        }

        if (is_array($value)) {
            return $default;
        }

        switch ($type) {
            case 'String':
                return $value ?? $default;
            case 'Int':
                return Typecast::int($value, $default);
            case 'Bool':
                return Typecast::bool($value, $default);
            case 'Regexp':
                $pattern = &$params[1];
                return preg_match($pattern, $value) ? $value : $default;
            case 'Enum':
                return in_array($value, $params[1]) ? $value : $default;
            default:
                return null;
        }
    }

    /**
     * Проверяем количество параметров, переданных в вызов магической функции
     *
     * Вспомогательный метод для магии получения значения из суперглобальной переменной (СГП)
     *
     * Максимум ожидаем 3 параметра. Если не задан тип, параметров может не быть. Иначе 1й параметр - всегда
     * обязательный. Необходимость 2го параметра зависит от типа: если тип в списке SINGLE_PARAM_TYPES, то параметр
     * необязательный. 3й параметр всегда необязательный.
     *
     * Сейчас всего два типа, требующих два обязательных параметра - Regexp и Enum. Поэтому выбор сообщения упрощен.
     *
     * @param string     $verb   http-глагог, которому соответствует СГП
     * @param string     $type   тип, к которому нужно привести значение из массива СГП
     * @param array|null $params переданные параметры
     * @return string|void
     */
    private static function checkRequiredParamsCount(string $verb, string $type, array $params)
    {
        $paramsCount = count($params);
        if (!$type || $paramsCount >= 2 || $paramsCount === 1 && in_array($type, self::SINGLE_PARAM_TYPES)) {
            return;
        }

        $message = 'Пропущен обязательный параметр функции: ';
        $message .= !$paramsCount
            ? "имя ключа в массиве \$_{$verb}"
            : ($type == 'Regexp' ? 'шаблон регулярного выражения.' : 'массив допустимых значений.');

        $secondParam = $type == 'Regexp' ? ', $pattern' : ($type == 'Enum' ? ', $expect' : '');
        $signatureCall = "Сигнатура вызова: {$verb}As{$type}(\$key{$secondParam})";

        return $message . PHP_EOL . $signatureCall;
    }

    /**
     * Получаем дефолтное значение, если оно есть
     *
     * Вспомогательный метод для магии получения значения из суперглобальной переменной (CГП)
     *
     * В параметрах, переданных в магию, может быть указано желаемое дефолтное значение. Количество передаваемых
     * параметров зависит от вызываемого магического метода, и дефолтное значение может быть вообще в них не указано.
     * Но если оно есть, то всегда последним параметром.
     *
     * @param string     $type   тип, к которому нужно привести значение из массива СГП
     * @param array|null $params переданные параметры
     * @return mixed|null
     */
    private static function getDefaultValue(string $type, array $params)
    {
        $paramsCount = count($params);
        return $paramsCount == 3 || $paramsCount == 2 && (!$type || in_array($type, self::SINGLE_PARAM_TYPES))
            ? array_pop($params)
            : null;
    }


    /**
     * Узкоспециализированный метод: получение заголовков запроса, переданных браузером
     *
     * Если требуется значение из конкретного заголовка, нужно указывать его в параметре. Будет выполнен
     * регистронезависимый поиск по заданному заголовку.
     *
     * Важно не путать http-заголовок и ключ в $_SERVER. Для примера: User-Agent - заголовок, HTTP_USER_AGENT - ключ.
     * Более того, в $_SERVER только ключи HTTP_* являются почти копией http-заголовков.
     *
     * @param null|string $key название заголовка
     * @return array|string|null
     */
    public static function headers(?string $key = null)
    {
        // В php-cli нет функции apache_request_headers(), как и самого веб-сервера
        if (System::isConsoleInterface()) {
            return null;
        }

        $headers = apache_request_headers();
        if (!$key) {
            return $headers;
        }

        $key = strtolower($key);
        foreach ($headers as $k => $v) {
            if (strtolower($k) == $key) {
                return $v;
            }
        }
        return null;
    }

    /**
     * Выяснить, с каким методом (http-глаголом) пришел запрос ИЛИ проверить, что метод совпадает с переданным
     * параметре.
     *
     * RESTful методы: GET, POST, DELETE, PUT. Есть еще OPTIONS, HEAD. Может еще какая-то экзотика.
     *
     * @param string $expect ожидаемый метод, без соблюдения регистра
     * @return null|string|bool
     */
    public static function method(string $expect = '')
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : null;
        if ($method && $expect) {
            return $method == strtoupper($expect);
        }
        return $method;
    }

    /**
     * Абсолютный URL текущей страницы
     *
     * Если нет заголовка 'HTTP_HOST', вернем NULL.
     *
     * @copyright  2007-2010 SARITASA LLC <info@saritasa.com>
     * @link       http://www.saritasa.com
     *
     * @return string|null
     */
    public static function absoluteURL(): ?string
    {
        if (!isset($_SERVER['HTTP_HOST'])) {
            return null;
        }

        $user = isset($_SERVER['PHP_AUTH_USER'])
            ? $_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW'] . '@'
            : '';

        return Env::scheme() . $user . $_SERVER['HTTP_HOST'] . Env::port() . self::relatedURL();

    }

    /**
     * Относительный адрес текущей страницы
     * @return string
     */
    public static function relatedURL(): string
    {
        $relatedUrl = '';

        if (isset($_SERVER['X_ORIGINAL_URL'])) {
            $relatedUrl = $_SERVER['X_ORIGINAL_URL'];
        } else if (isset($_SERVER['X_REWRITE_URL'])) {
            $relatedUrl = $_SERVER['X_REWRITE_URL'];
        } else if (isset($_SERVER['IIS_WasUrlRewritten'])
            && $_SERVER['IIS_WasUrlRewritten'] == '1'
            && !empty($_SERVER['UNENCODED_URL'])
        ) {
            $relatedUrl = $_SERVER['UNENCODED_URL'];
        } else if (isset($_SERVER['REQUEST_URI'])) {
            $relatedUrl = $_SERVER['REQUEST_URI'];
        } else if (isset($_SERVER['ORIG_PATH_INFO'])) {
            $relatedUrl = $_SERVER['ORIG_PATH_INFO'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $relatedUrl .= '?' . $_SERVER['QUERY_STRING'];
            }
        } else if (isset($_SERVER['PHP_SELF'])) {
            $relatedUrl = $_SERVER['PHP_SELF'];
        }

        return $relatedUrl;
    }

    /**
     * Получение ip юзера.
     *
     * @link http://stackoverflow.com/questions/15699101/get-the-client-ip-address-using-php
     * @return string|null
     */
    public static function userIP()
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
    public static function isAjax()
    {
        return self::headers('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Получение инфы по User Agent, передаваемой браузером в соответствующем заголовке. Если заголовка нет, вернется
     * NULL.
     *
     * Парсинг заголовка выполняется библиотекой {@link https://github.com/donatj/PhpUserAgent PhpUserAgent}
     *
     * @param bool $raw вернуть значение заголовка, как есть, без парсинга
     * @return mixed
     * @throws RequestException
     */
    public static function userAgentInfo($raw = false)
    {
        $agentInfo = self::headers('User-Agent');

        if ($raw || !$agentInfo) {
            return $agentInfo;
        }

        if (!function_exists('parse_user_agent')) {
            throw new RequestException('Не найдена функция для парсинга user agent');
        }

        return parse_user_agent($agentInfo);
    }

    /**
     * Проверяем, что запрос пришел с мобильного браузера.
     *
     * Используемая регулярка взята здесь {@link http://detectmobilebrowsers.com}
     *
     * @return bool
     */
    public static function isMobileBrowser()
    {
        if (!$userAgent = self::userAgentInfo(true)) {
            return false;
        }

        return (bool)(preg_match(
                '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',
                $userAgent)
            || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',
                substr($userAgent, 0, 4))
        );
    }
}
