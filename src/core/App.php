<?php
namespace kira\core;

use Composer\Autoload\ClassLoader;
use kira\configuration\PhpConfigProvider;
use \kira\configuration\ConfigManager;
use kira\net\AbstractRouter;
use kira\net\Router;
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
     * Название и версия движка. Ссылка на оф.сайт движка
     */
    const
        ENGINE_NAME = 'Kira Web Engine',
        VERSION = '2.0',
        ENGINE_URL = 'https://github.com/VijitCoder/Kira';

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
     * Shortcut-метод: чтение конкретной настройки конфига
     *
     * Можно указать цепочку вложенных ключей, через точку. Типа: "validators.password.minComb". Возвращено будет
     * значение последнего ключа. Очевидно, что использовать точку в именах ключей конфига теперь нельзя.
     *
     * @param string $key    ключ в конфиге
     * @param bool   $strict флаг критичности реакции, когда настройка не найдена: TRUE = пробросить исключение
     * @return mixed|null
     */
    public static function conf($key, $strict = true)
    {
        return self::configManager()->getValue($key, $strict);
    }

    /**
     *  Shortcut-метод: замена значения в конфиге "налету"
     *
     * Ключ может быть составным, через точку (по аналогии с чтением конфига). Новое значение <b>перезаписывает</b>
     * текущее.
     *
     * @param string $key   ключ в конфиге
     * @param mixed  $value новое значение
     */
    public static function changeConf($key, $value): void
    {
        self::configManager()->setValue($key, $value);
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
     * @param array  $ins массив замены/вставки в текстах. Формат ключей - вообще любой, главное чтоб с простым текстом
     *                    фразы не совпало.
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
     *
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
     * Проверить, знает ли автозагрузчик указанный класс.
     *
     * @param string $className FQN класса для проверки. Ведущий слеш не имеет значения.
     * @return bool
     * @throws \LogicException
     */
    public static function isKnownClass(string $className): bool
    {
        if (!isset(self::$instances['composer'])) {
            throw new \LogicException('Не задан экземпляр класса ClassLoader');
        }

        $composer = self::$instances['composer'];
        $className = ltrim($className, '\\');
        return (bool)$composer->findFile($className);
    }

    /**
     * Получение инстанциированного объекта менеджера конфигурации
     *
     * TODO: тут нужно определять, какой тип конфигурации используется (php, yaml, json, etc.) и создавать
     * соответствующий объект поставщика конфигурации. Как именно определять, пока не придумал.
     *
     * @return ConfigManager
     */
    public static function configManager(): ConfigManager
    {
        if (!isset(self::$instances['configManager'])) {
            $provider = new PhpConfigProvider(KIRA_MAIN_CONFIG);
            self::$instances['configManager'] = ConfigManager::getInstance($provider);
        }

        return self::$instances['configManager'];
    }

    /**
     * Получение инстанциированного объекта роутера
     *
     * @return AbstractRouter конкретная реализация абстрактного класса, потомок
     */
    public static function router()
    {
        if (!isset(self::$instances['router'])) {
            $router = self::conf('router.class', false) ?: Router::class;
            self::$instances['router'] = new $router();
        }
        return self::$instances['router'];
    }

    /**
     * Получение инстанциированного объекта логера
     *
     * @return AbstractLogger конкретная реализация абстрактного класса, потомок
     */
    public static function logger()
    {
        if (!isset(self::$instances['logger'])) {
            $logger = self::conf('logger.class', false) ?: Logger::class;
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
