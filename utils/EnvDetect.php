<?php
/**
 * Определение окружения (local, dev, stage, production, mobile). Любым возможным способом: анализ заголовков, переменных
 * среды ОС, спец.файлы где-нибудь, проверка ip, схемы, домена, доп.конфиг. Что угодно, в зависимости от реального
 * окружения.
 */

namespace utils;

abstract class EnvDetect
{
    //Домен. Константы введены для устранения "magic string"
    const D_LOCAL = 0;
    const D_DEV = 1;
    const D_STAGE = 2;
    const D_PROD = 3;
    const D_MOBILE = 4;

    /**
     * Определение среды окружения.
     *
     * Метод абстрактный и должен быть реализован в приложении. PHP не дает объявить его одновременно static и abstract.
     * Поэтому просто пустая реализация.
     *
     * @return int
     */
    protected static function detectEnvironment() {}

    # Геттеры для выяснения среды окружения

    public static final function isLocal()
    {
        return static::detectEnvironment() === self::D_LOCAL;
    }

    public static final function isDevelopment()
    {
        return static::detectEnvironment() === self::D_DEV;
    }

    public static final function isStage()
    {
        return static::detectEnvironment() === self::D_STAGE;
    }

    public static final function isProduction()
    {
        return self::detectEnvironment() === self::D_PROD;
    }

    public static final function isMobile()
    {
        return static::detectEnvironment() === self::D_MOBILE;
    }
}
