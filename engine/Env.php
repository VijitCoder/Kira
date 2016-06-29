<?php
/**
 * Среда окружения сайта.
 */

namespace engine;

class Env implements IDetectEnvironment
{
    /**
     * Схема вместе с '://'
     *
     * Схема не всегда может быть определена, это зависит от настроек сервера. Если не получилось ее выяснить,
     * считаем 'http'.
     *
     * @return string
     */
    public static function scheme()
    {
        $https = isset($_SERVER['HTTPS']) && (strcasecmp($_SERVER['HTTPS'], 'on') === 0 || $_SERVER['HTTPS'] == 1)
            || isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strcasecmp($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') === 0;

        return $https ? 'https://' : 'http://';
    }

    /**
     * Имя домена, типа "my.site.com".
     *
     * Пытаемся его получить из $_SERVER['HTTP_HOST']. Иначе ищем в настройках приложения ('domain').
     *
     * @return string
     */
    public static function domainName()
    {
        return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (App::conf('domain', false) ? : '');
    }

    /**
     * Порт, на котором работает сайт.
     *
     * Если порт 80, тогда вернем пустую строку. Иначе ведущее двоеточие и номер порта. Двоеточие конечно не относится
     * к порту, это для удобства сборки адреса.
     *
     * @return string
     */
    public static function port()
    {
        return isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != 80
            ? ':' . $_SERVER['SERVER_PORT']
            : '';
    }

    /**
     * Схема://домен:порт. Без слеша в конце.
     *
     * @return string|null
     */
    public static function domainUrl()
    {
        if (!$host = self::domainName()) {
            return null;
        }
        return self::scheme() . $host . self::port();
    }

    /**
     * Главная страница сайта.
     * Возможно в потомках потребуется переопределение метода.
     *
     * @return string|null
     */
    public static function indexPage()
    {
        return self::domainUrl() . '/';
    }

    /**
     * Определение среды окружения (local, dev, stage, production, mobile).
     *
     * Метод должен быть реализован в конкретном приложении, если будут использоваться ниже приведенные геттеры.
     * По сути, это абстрактный метод. Но ограничения PHP не позволяют объявить абстрактным статический метод. Делать
     * его динамическим - невыгодно.
     *
     * @return int см. константы "D_*" в IDetectEnvironment.
     */
    public static function detectEnvironment()
    {
        return -1;
    }

    # Геттеры для выяснения среды окружения

    public static final function isLocal()
    {
        return static::detectEnvironment() === IDetectEnvironment::D_LOCAL;
    }

    public static final function isDevelopment()
    {
        return static::detectEnvironment() === IDetectEnvironment::D_DEV;
    }

    public static final function isStage()
    {
        return static::detectEnvironment() === IDetectEnvironment::D_STAGE;
    }

    public static final function isProduction()
    {
        return self::detectEnvironment() === IDetectEnvironment::D_PROD;
    }

    public static final function isMobile()
    {
        return static::detectEnvironment() === IDetectEnvironment::D_MOBILE;
    }
}
