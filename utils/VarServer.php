<?php
/**
 * Глобальная переменная $_SERVER.
 * Некоторые геттеры для удобства.
 */

namespace utils;

class VarServer
{
    /**
     * Имя домена
     * @return string
     */
    public static function domain()
    {
        return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
    }

    /**
     * Домен + схема.С конечным слешем
     * @return string
     */
    public static function domainWithScheme()
    {
        $scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
        $host = self::domain();
        return "$scheme://$host/";
    }

    /**
     * Главная страница сайта. Пока это просто синоним другой функции, для удобства понимания кода.
     *
     * @return string
     */
    public static function indexPage()
    {
        return self::domainWithScheme();
    }

    /**
     * Абсолютный URL запроса клиента.
     * @return string
     */
    public static function requestURL()
    {
        return self::domainWithScheme() . ltrim($_SERVER['REQUEST_URI'], '/');
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
            getenv('HTTP_CLIENT_IP')?:
            getenv('HTTP_X_FORWARDED_FOR')?:
            getenv('HTTP_X_FORWARDED')?:
            getenv('HTTP_FORWARDED_FOR')?:
            getenv('HTTP_FORWARDED')?:
            getenv('REMOTE_ADDR')?:
            '';
    }
}
