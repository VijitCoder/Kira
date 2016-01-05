<?php
/**
 * Обертка для $_REQUEST ($_GET, $_POST и $_COOKIE)
 *
 * @TODO может стоит завести отдельные классы на каждую переменную?
 */

namespace utils;

class VarRequest
{
    public static function get($name)
    {
        return (isset($_GET[$name])) ? $_GET[$name] : null;
    }

    public static function getInt($name)
    {
        return intval(self::get($name));
    }

    public static function getBool($name)
    {
        $var = self::get($name);
        return preg_match('~^true|1|on|checked|истина|да$~u', $var) ? true : false;
    }

    public static function getRegexp($name, $pattern)
    {
        $var = self::get($name);
        return preg_match($pattern, $var) ? $var : null;
    }

    public static function getCookie()
    {
    }

    public static function setCookie()
    {
    }
}
