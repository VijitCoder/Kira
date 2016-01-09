<?php
/**
 * Запрос клиента.
 *
 * Обертка для $_GET; пара методов для cookie; получение полного адреса запроса, ip юзера и т.п.
 *
 * Прим.: в PECL есть расширение с похожим назначением {@see http://php.net/manual/en/class.httprequest.php}. Но допустим,
 * нам так много не нужно :)
 *
 * @TODO описания к методам
 */

namespace utils;

class Request
{
    public static function GET($name)
    {
        return (isset($_GET[$name])) ? $_GET[$name] : null;
    }

    public static function GET_int($name)
    {
        return intval(self::get($name));
    }

    public static function GET_bool($name)
    {
        $var = self::get($name);
        return preg_match('~^true|1|on|checked|истина|да$~u', $var) ? true : false;
    }

    public static function GET_regexp($name, $pattern)
    {
        $var = self::get($name);
        return preg_match($pattern, $var) ? $var : null;
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
    public function setDefaultCookie($name, $value)
    {
        setcookie($name, $value, time() + 31536000, '/', '.' . Env::domain(), false, true);
    }

    /**
     * Запрос печеньки из массива $_COOKIE.
     *
     * @param $name имя печеньки
     * @return string|null
     */
    public function getCookie($name)
    {
        return (isset($_COOKIE[$name])) ? $_COOKIE[$name] : null;
    }

    /**
     * Абсолютный URL текущей страницы.
     *
     * Если был не http-запрос, вернем NULL.
     *
     * @copyright  2007-2010 SARITASA LLC <info@saritasa.com>
     * @link       http://www.saritasa.com
     *
     * @return string|null
     */
    public static function absoluteURL()
    {
        if (!isset($_SERVER['HTTP_HOST'])) {
            return false;
        }

        if (!isset($_SERVER['HTTPS']) && isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $url = $_SERVER['HTTP_X_FORWARDED_PROTO'] . '://';
        } else {
            $url = empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off' ? 'http://' : 'https://';
        }

        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $url .= $_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW'] . '@';
        }

        $url .= $_SERVER['HTTP_HOST'];

        if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != 80) {
            $url .= ':' . $_SERVER['SERVER_PORT'];
        }

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
     * К $_SERVER имеет косвенное отношение, логично разместить метод тут.
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
     * Следует помнить, что js-код должен явно передать нужный заголовок, хотя это не требуется стандартом XMLHttpRequest.
     * Например, JQuery.ajax это делает, а некоторые другие реализации стандарта - нет. Поэтому метод назван
     * именно "..ajax".
     *
     * @return bool
     */
    public static function isAjax()
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
    public static function isMobileBrowser()
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
