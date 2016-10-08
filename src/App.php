<?php
namespace kira;

use Exception;

/**
 * Базовый класс. Будет что-то общее у многих - можно разместить тут.
 */
class App
{
    /** Название и версия движка. Ссылка на оф.сайт движка */
    const VERSION = 'Kira 1.2';
    const ENGINE_URL = 'https://github.com/VijitCoder/Kira';

    /** @var array конфигурация приложения */
    private static $config;

    /** @var array словарь локализации */
    private static $lexicon;

    /** @var string заданный файл локализации. Инфа для проброса исключения */
    private static $lang;

    /** @var array объекты классов, инстанциированных через App: роутер, логер */
    private static $instances = [];

    /**
     * Чтение конкретной настройки конфига.
     *
     * Можно указать цепочку вложенных ключей, через точку. Типа: "validators.password.minComb". Возвращено будет
     * значение последнего ключа. Очевидно, что использовать точку в именах ключей конфига теперь нельзя.
     *
     * @param string $key    ключ в конфиге
     * @param bool   $strict флаг "критичности", когда настройка не найдена: TRUE = пробросить исключение
     * @return mixed|null
     * @throws \Exception
     */
    public static function conf($key, $strict = true)
    {
        if (!self::$config) {
            self::$config = require MAIN_CONFIG;
        }

        $level = self::$config;
        foreach(explode('.', $key) as $k => $part) {
            if (!isset($level[$part])) {
                if ($strict) {
                    throw new Exception("В конфигурации не найден ключ '{$part}'");
                } else {
                    return null;
                }
            }

            $level = $level[$part];
        }

        return $level;
    }

    /**
     * Определяем язык интерфейса
     *
     * @return string ru|en и т.д.
     */
    public static function detectLang()
    {
        if (!self::$lang) {
            $langPath = APP_PATH . 'i18n/';
            $lang = 'ru';
            if (isset($_COOKIE['lang'])) {
                $try = $_COOKIE['lang'];
                if (preg_match('~[a-z]{2,3}~i', $try) && file_exists("{$langPath}{$try}.php")) {
                    $lang = $try;
                }
            }
            self::$lang = $lang;
        }

        return self::$lang;
    }

    /**
     * Переводчик.
     *
     * При первом обращении к передводчику определяем, какой выбран язык, загружаем нужный словарь.
     *
     * По умолчанию принят русский. Для него нет словаря. Как создавать локализацию: заводим файл словаря,
     * переключаемся на язык, находим непереведенные фразы. Они станут ключами в массиве словаря. Значения
     * массива - те же фразы, но на заданном языке. Все просто :)
     *
     * @param string $key фраза на русском языке
     * @param array  $ins массив замены/вставки в текстах. Формат ключей - вообще любой, главное чтоб
     *                    с простым текстом фразы не совпало.
     * @return string фраза в заданном языке
     */
    public static function t($key, $ins = array())
    {
        if (!self::$lexicon) {
            $langPath = APP_PATH . 'i18n/';
            $lang = self::detectLang();
            self::$lexicon = $lang == 'ru' ? array() : require_once "{$langPath}{$lang}.php";
        }

        $str = (self::$lang == 'ru' || !isset(self::$lexicon[$key])) ? $key : self::$lexicon[$key];
        if ($ins) {
            $str = str_replace(array_keys($ins), $ins, $str);
        }

        return $str;
    }

    /**
     * Завершение приложения. Последние процедуры после отправки ответа браузеру.
     *
     * @param callable $callback функция, которую следует выполнить перед выходом.
     * @param string   $msg сообщение на выходе
     */
    public static function end($callback = null, $msg = '')
    {
        if ($callback) {
            call_user_func($callback);
        }
        exit($msg);
    }

    /**
     * Возвращает объект класса текущего роутера.
     *
     * Роутер движка может быть заменен частной реализацией, в которой согласно IRouter должна быть своя реализация
     * метода url(). Чтобы в клиентском коде не выяснять, кто - текущий роутер, введена эта функция.
     *
     * @return object реализация интерфейса IRouter, объект класса роутера
     */
    public static function router()
    {
        if (!isset(self::$instances['router'])) {
            $router = self::conf('router.class', false) ?: 'kira\net\Router';
            self::$instances['router'] = new $router;
        }
        return self::$instances['router'];
    }

    /**
     * Возвращает объект логера.
     *
     * В зависимости от настроек и доступности базы, логирование может вестись в БД или файлы. Если таблица окажется
     * недоступна, будем сбрасывать логи в файлы. Чтоб в течение работы приложения не выяснять на каждом логе факт
     * доступности базы, используем этот геттер.
     *
     * @return \kira\Log
     */
    public static function log()
    {
        if (!isset(self::$instances['log'])) {
            self::$instances['log'] = new \kira\Log;
        }
        return self::$instances['log'];
    }
}
