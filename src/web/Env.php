<?php
namespace kira\web;

use kira\interfaces\IDetectEnvironment;
use kira\core\App;

/**
 * Среда окружения сайта
 *
 * См. документацию, "Среда окружения сайта"
 */
class Env
{
    /**
     * Среда окружения
     */
    const
        ENV_UNKNOWN = 'unknown',
        ENV_LOCAL = 'local',
        ENV_DEV = 'dev',
        ENV_STAGE = 'stage',
        ENV_PROD = 'production',
        ENV_MOBILE = 'mobile',
        ENV_UNIT = 'unit'; // модульное тестирование

    /**
     * Определение среды окружения (local, dev, production и т.д.)
     *
     * Метод должен быть реализован в конкретном приложении, если будут использоваться ниже приведенные геттеры.
     * По сути, это абстрактный метод. Но ограничения PHP не позволяют объявить абстрактным статический метод. Делать
     * его динамическим - невыгодно.
     *
     * @return string см. константы ENV_* в IDetectEnvironment.
     */
    public static function detectEnvironment()
    {
        return IDetectEnvironment::ENV_UNKNOWN;
    }

    /**
     * Сайт на локалке
     *
     * @return bool
     */
    public static final function isLocal()
    {
        return static::detectEnvironment() === IDetectEnvironment::ENV_LOCAL;
    }

    /**
     * Сайт на деве
     *
     * @return bool
     */
    public static final function isDevelopment()
    {
        return static::detectEnvironment() === IDetectEnvironment::ENV_DEV;
    }

    /**
     * Сайт на staging
     *
     * @return bool
     */
    public static final function isStage()
    {
        return static::detectEnvironment() === IDetectEnvironment::ENV_STAGE;
    }

    /**
     * Сайт на проде
     *
     * @return bool
     */
    public static final function isProduction()
    {
        return self::detectEnvironment() === IDetectEnvironment::ENV_PROD;
    }

    /**
     * Сайт на спец.домене для мобильных устройств
     *
     * @return bool
     */
    public static final function isMobile()
    {
        return static::detectEnvironment() === IDetectEnvironment::ENV_MOBILE;
    }

    /**
     * Приложение выполняется в тестовом окружении (unit-тесты)
     *
     * @return bool
     */
    public static final function isUnit()
    {
        return static::detectEnvironment() === IDetectEnvironment::ENV_UNIT;
    }

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
     * Имя домена, типа "my.site.com"
     *
     * Пытаемся его получить из $_SERVER['HTTP_HOST']. Иначе ищем в настройках приложения ('domain').
     *
     * @return string
     */
    public static function domainName()
    {
        return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (App::conf('domain', false) ?: '');
    }

    /**
     * Порт, на котором работает сайт
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
     * Схема://домен:порт. Без слеша в конце
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
     * Главная страница сайта
     *
     * Возможно в потомках потребуется переопределение метода.
     *
     * @return string|null
     */
    public static function indexPage()
    {
        return self::domainUrl() . '/';
    }
}
