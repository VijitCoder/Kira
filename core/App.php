<?
/**
 * Базовый класс. Будет что-то общее у многих - можно разместить тут.
 */
class App
{
    /** @var array конфигурация приложения */
    private static $config;

    /** @var array словарь локализации */
    private static $_lexicon;

    /** @var string заданный файл локализации. Инфа для проброса исключения */
    private static $_lang;

    /**
     * Чтение конкретной настройки конфига
     * @param string $key
     * @return mixed
     */
    public static function conf($key)
    {
        if (!self::$config) {
            self::$config = require PATH_APP . 'conf/main.php';
        }

        if (!isset(self::$config[$key])) {
            throw new Exception("В конфигурации не найден ключ '{$key}'");
        }
        return self::$config[$key];
    }

    /**
     * Определяем язык интерфейса
     * @return string ru|en и т.д.
     */
    public static function detectLang()
    {
        if (!self::$_lang) {
            $langPath = PATH_ROOT . 'i18n/';
            $lang = 'ru';
            if (isset($_COOKIE['lang'])) {
                $try = $_COOKIE['lang'];
                if (preg_match('~[a-z]{2,3}~i', $try) && file_exists("{$langPath}{$try}.php")) {
                    $lang = $try;
                }
            }
            self::$_lang = $lang;
        }

        return self::$_lang;
    }

    /**
     * Переводчик
     * По умолчанию принят русский. Для него нет словаря. Как создавать локализацию: заводим файл словаря,
     * переключаемся на язык, находим непереведенные фразы. Они станут ключами в массиве словаря. Значения
     * массива - те же фразы, но на заданном языке. Все просто :)
     *
     * @param string $key фраза на русском языке
     * @param array $ins массив замены/вставки в текстах. Формат ключей - вообще любой, главное чтоб
     * с простым текстом фразы не совпало.
     * @return string фраза в заданном языке
     */
    public static function t($key, $ins = array())
    {
        //загружаем словарь, если этого еще не делали
        if (!self::$_lexicon) {
            $langPath = PATH_ROOT . 'i18n/';
            $lang = self::detectLang();
            self::$_lexicon = $lang == 'ru' ? array() : require_once "{$langPath}{$lang}.php";
        }

        //поиск перевода. Русского словаря нет, он без перевода работает
        $str = (self::$_lang=='ru' || !isset(self::$_lexicon[$key])) ? $key : self::$_lexicon[$key];
        if ($ins) {
            $str = str_replace(array_keys($ins), $ins, $str);
        }

        return $str;
    }

    /**
     * Перехват исключений. Самый простой вариант, только показать сообщение.
     * После выполнения этого обработчика программа остановится, обеспечено PHP.
     *
     * @param $ex Exception
     * @return void
     */
    public static function exceptionHandler($ex)
    {
        if (DEBUG) {
            echo '<html>
                    <head><meta charset="utf-8"></head><body>

                    <h4>App handled exception</h4>

                    <table class="xdebug-error xe-parse-error" border=1 cellspacing=0 cellpadding=1>'
                    . $ex->xdebug_message .
                    '</table>
                     <p>End of message.</p>
                 </body></html>';
        } else {
           //var_dump($ex);
            //@TODO лог, мыло..
        }
    }

    /**
     * Завершение приложения. Послeдние процедуры после отправки ответа браузеру
     * @param string $msg сообщение на выходе
     */
    public static function end($msg = '')
    {
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        exit($msg);
    }
}
