<?php
namespace kira\core;

use Composer\Autoload\ClassLoader;
use kira\exceptions\ConfigException;
use kira\net\AbstractRouter;
use kira\utils\Registry;

/**
 * Базовый класс движка. Очень общие методы.
 *
 * См. документацию, "Обобщение".
 */
class App
{
    use Singleton;

    /**
     * Версия движка. Ссылка на оф.сайт движка
     */
    const VERSION = '1.5';
    const ENGINE_URL = 'https://github.com/VijitCoder/Kira';

    /**
     * @var array конфигурация приложения
     */
    private static $config;

    /**
     * @var array словарь локализации. Внутренний кеш класса
     */
    private static $lexicon;

    /**
     * @var string заданный файл локализации
     */
    private static $lang;

    /**
     * @var array объекты классов, инстанциированных через App. Внутренний кеш класса
     */
    private static $instances = [];

    /**
     * Чтение конкретной настройки конфига
     *
     * Можно указать цепочку вложенных ключей, через точку. Типа: "validators.password.minComb". Возвращено будет
     * значение последнего ключа. Очевидно, что использовать точку в именах ключей конфига теперь нельзя.
     *
     * @param string $key    ключ в конфиге
     * @param bool   $strict флаг критичности реакции, когда настройка не найдена: TRUE = пробросить исключение
     * @return mixed|null
     * @throws ConfigException
     */
    public static function conf($key, $strict = true)
    {
        if (!self::$config) {
            self::$config = require KIRA_MAIN_CONFIG;
        }

        $result = self::$config;
        foreach (explode('.', $key) as $levelKey) {
            if (!isset($result[$levelKey])) {
                if ($strict) {
                    throw new ConfigException("В конфигурации не найден ключ '{$levelKey}'");
                } else {
                    return null;
                }
            }

            $result = $result[$levelKey];
        }

        return $result;
    }

    /**
     * Замена значения в конфиге "налету"
     *
     * Этой функцией можно поменять значение в конфиге приложения, но только если ключ уже существует. Такое ограничение
     * введено для предотвращения попытки сохранить в конфиге произвольное глобальное значение. Для этого есть Реестр
     * (kira\utils\Registry).
     *
     * Ключ может быть составным, через точку (по аналогии с чтением конфига). Новое значение <b>перезаписывает</b>
     * текущее.
     *
     * @param string $key   ключ в конфиге
     * @param mixed  $value новое значение
     * @throws ConfigException
     */
    public static function changeConf($key, $value)
    {
        if (!$key) {
            throw new ConfigException('Ключ в конфигурации не может быть пустым.');
        }

        if (!self::$config) {
            self::$config = require KIRA_MAIN_CONFIG;
        }

        $conf = &self::$config;
        $keys = explode('.', $key);
        for ($i = 0, $cnt = count($keys); $i < $cnt; ++$i) {
            $levelKey = $keys[$i];
            if (!isset($conf[$levelKey])) {
                throw new ConfigException("В конфигурации не найден ключ '{$levelKey}'");
            }
            if ($i < $cnt - 1) {
                $conf = &$conf[$levelKey];
            }
        }
        $conf[$levelKey] = $value;
    }

    /**
     * Определяем язык приложения
     *
     * Читаем cookie "lang". Ожидаем 2-3 буквы латиницы, обозначающие выбранный язык.
     *
     * @return string ru|en и т.д.
     */
    public static function detectLang()
    {
        if (!self::$lang) {
            $langPath = KIRA_APP_PATH . 'i18n/';
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
     * Переводчик
     *
     * При первом обращении к переводчику определяем, какой выбран язык, загружаем нужный словарь. По умолчанию принят
     * русский. Для него нет словаря.
     *
     * @param string $key фраза на русском языке
     * @param array $ins массив замены/вставки в текстах. Формат ключей - вообще любой, главное чтоб с простым текстом
     *                   фразы не совпало.
     * @return string фраза в заданном языке
     */
    public static function t($key, $ins = array())
    {
        if (!self::$lexicon) {
            $langPath = KIRA_APP_PATH . 'i18n/';
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
     * @param callable $callback функция, которую следует выполнить перед выходом.
     * @param string   $msg      сообщение на выходе
     */
    public static function end($callback = null, $msg = '')
    {
        if ($callback) {
            call_user_func($callback);
        }
        exit($msg);
    }

    /**
     * Запоминаем экземпляр автозагрузчика Composer, который управляет нашей автозагрузкой.
     *
     * Метод обычно вызывается в главном index.php при подключении Composer.
     *
     * @internal
     * @param ClassLoader $composer экземпляр класса автозагрузчика Composer
     */
    public static function setComposer($composer)
    {
        self::$instances['composer'] = $composer;
    }

    /**
     * Получить экземпляр класса текущего загрузчика
     * @return ClassLoader
     * @throws \LogicException
     */
    public static function composer()
    {
        if (!isset(self::$instances['composer'])) {
            throw new \LogicException('Не задан экземпляр класса ClassLoader');
        }
        return self::$instances['composer'];
    }

    /**
     * Получение инстанциированного объекта роутера
     * @return AbstractRouter конкретная реализация абстрактного класса, потомок
     */
    public static function router()
    {
        if (!isset(self::$instances['router'])) {
            $router = self::conf('router.class', false) ?: 'kira\net\Router';
            self::$instances['router'] = new $router();
        }
        return self::$instances['router'];
    }

    /**
     * Получение инстанциированного объекта логера
     * @return AbstractLogger конкретная реализация абстрактного класса, потомок
     */
    public static function logger()
    {
        if (!isset(self::$instances['logger'])) {
            $logger = self::conf('logger.class', false) ?: 'kira\core\Logger';
            self::$instances['logger'] = new $logger();
        }
        return self::$instances['logger'];
    }

    /**
     * Обращение к реестру
     *
     * Это просто обертка для вызова метода
     *
     * @return Registry
     */
    public static function registry()
    {
        return Registry::getInstance();
    }
}
