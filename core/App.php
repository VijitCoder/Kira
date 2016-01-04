<?
/**
 * Базовый класс. Будет что-то общее у многих - можно разместить тут.
 */

namespace core;

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
     *
     * @param string $key ключ в конфиге
     * @param bool   $strict флаг "критичности", когда настройка не найдена: TRUE = пробросить исключение
     * @return mixed
     * @throws Exception
     */
    public static function conf($key, $strict = true)
    {
        if (!self::$config) {
            self::$config = require MAIN_CONFIG;
        }

        if (!isset(self::$config[$key])) {
            if ($strict) {
                throw new Exception("В конфигурации не найден ключ '{$key}'");
            } else {
                return null;
            }
        }
        return self::$config[$key];
    }

    /**
     * Определяем язык интерфейса
     *
     * @return string ru|en и т.д.
     */
    public static function detectLang()
    {
        if (!self::$_lang) {
            $langPath = ROOT_PATH . 'i18n/';
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
     * Переводчик.
     *
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
            $langPath = ROOT_PATH . 'i18n/';
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
     * Перехватчик исключений.
     *
     * Ловит исключения, которые не были пойманы ранее. Последний шанс обработать ошибку. Например,
     * записать в лог или намылить админу. Можно так же вежливо откланяться юзеру.
     *
     * После выполнения этого обработчика программа остановится, обеспечено PHP.
     *
     * @param Exception $ex
     */
    public static function exceptionHandler($ex)
    {
        $wrapper = "<html>\n<head>\n<meta charset='utf-8'>\n</head>\n\n<body>\n%s\n</body>\n\n</html>";
        if (DEBUG) {
            $err = '<h3>'.get_class($ex)."</h3>\n"
                 . sprintf("<p><i>%s</i></p>\n", $ex->getMessage())
                 . sprintf("<p>%s:%d</p>\n", str_replace(ROOT_PATH, '/', $ex->getFile()), $ex->getLine())
                 . '<pre>' . $ex->getTraceAsString() . '</pre>';
            printf($wrapper, $err);
        } else  {
            $err = "<h3>Упс! Произошла ошибка</h3>\n"
                 //. '<p><i>' . $ex->getMessage() . "</i></p>\n"
                 . '<p>Зайдите позже, пожалуйста.</p>';

            printf($wrapper, $err);
            //@TODO логирование, письмо/смс админу.
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
