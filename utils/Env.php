<?php
/**
 * Среда окружения сайта.
 */

namespace utils;

use core\App;

class Env implements IDetectEnvironment //интерфейс в этом же скрипте
{
    /**
     * Имя домена.
     *
     * Пытаемся его получить из переменной $_SERVER. В крайнем случае ищем в настройках приложения ('domain').
     *
     * @return string|null
     */
    public static function domain()
    {
        return isset($_SERVER['HTTP_HOST'])
            ? $_SERVER['HTTP_HOST']
            : isset($_SERVER['SERVER_NAME'])
                ? $_SERVER['SERVER_NAME']
                : App::conf('domain', false)
            ;
    }

    /**
     * Домен + схема. С конечным слешем.
     *
     * Схема не всегда может быть определена, это зависит от настроек сервера. Если не получилось ее выяснить,
     * считаем 'http'.
     *
     * @return string|null
     */
    public static function domainWithScheme()
    {
        if (!$host = self::domain()){
            return null;
        }
        $scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
        return "$scheme://$host/";
    }

    /**
     * Главная страница сайта. Пока это просто синоним другой функции, для удобства понимания кода.  Возможно потребуется
     * переопределение метода в потомках.
     *
     * @return string|null
     */
    public static function indexPage()
    {
        return self::domainWithScheme();
    }

    /**
     * Определение среды окружения (local, dev, stage, production, mobile).
     *
     * Пустышка. Метод должен быть реализован в конкретном приложении, если будут использоваться ниже приведенные геттеры.
     *
     * @return int см. константы "D_*" в этом классе.
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

interface IDetectEnvironment
{
    //Домен. Константы введены для устранения "magic string"
    const D_LOCAL = 0;
    const D_DEV = 1;
    const D_STAGE = 2;
    const D_PROD = 3;
    const D_MOBILE = 4;

    /**
     * Определение среды окружения (local, dev, stage, production, mobile).
     *
     * Любым возможным способом: анализ заголовков, переменных среды ОС, спец.файлы где-нибудь, проверка ip, схемы,
     * домена, доп.конфиг. Что угодно, в зависимости от реального окружения..
     *
     * Метод должен быть реализован в конкретном приложении, если будут использоваться ниже приведенные геттеры.
     *
     * @return int см. константы "D_*" в этом классе.
     */
    public static function detectEnvironment();

    # Геттеры для выяснения среды окружения

    /**
     * Сайт на локалке
     * @return bool
     */
    public static function isLocal();

    /**
     * Сайт на деве
     * @return bool
     */
    public static function isDevelopment();

    /**
     * Сайт на staging
     * @return bool
     */
    public static function isStage();

    /**
     * Сайт на проде
     * @return bool
     */
    public static function isProduction();

    /**
     * Сайт на спец.домене для мобильных устройств
     * @return bool
     */
    public static function isMobile();
}
