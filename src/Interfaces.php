<?php
/**
 * Интерфейсы. Все в одном месте.
 */

namespace kira;

/**
 * Интерфейс роутера.
 *
 * В конструктор роутера передается экземпляр автозагрузчика Composer. В роутере можно использовать его API.
 */
interface IRouter
{
    /**
     * Парсинг URL и вызов action-метода в соответствующем контроллере.
     * @return void
     */
    public function callAction();

    /**
     * Построение URL по описанию.
     *
     * @param mixed $route  какое-то определение роута. Например controller/action
     * @param array $params доп.параметры для передачи в адрес. Ассоциативный массив ['имя параметра' => 'значение']
     * @return string готовый <b>относительный</b> URL
     */
    public function url($route, array $params = []);

    /**
     * Названия контроллера, к которому обратился роутер после парсинга запроса
     * @return string
     */
    public function getController();

    /**
     * Названия метода-действия, которое вызвал роутер после парсинга запроса
     * @return string
     */
    public function getAction();
}

/**
 * Интерфейс логера
 *
 * Создан в основном для возможности подмены реазлизации на mock-объект в unit-тестах. Поэтому так коряво выглядит.
 * В целом логер запилен в движке и подробности см. в его реализации.
 */
interface ILogger
{
    /**
     * Типы логов
     */
    const
        ENGINE = 'engine',
        DB_CONNECT = 'DB connection',
        DB_QUERY = 'DB query',
        EXCEPTION = 'exception',
        HTTP_ERROR = 'HTTP error', // например, 404, 403 можно логировать
        INFO = 'information',
        UNTYPED = 'untyped';

    /**
     * Хранение лога.
     *
     * Эти константы используются только в настройках приложения и задают поведение логера по умолчанию. В текущей
     * реализации интерфейса логера в случае сбоя переключаемся на вышестоящий. При 0 - только письмо админу.
     */
    const
        STORE_ERROR = 0,
        STORE_IN_FILES = 1,
        STORE_IN_DB = 2;

    /**
     * Запись сообщения в лог
     *
     * Ожидаем либо строку с сообщением либо массив, содержащий сообщение и другие данные. Полный формат массива такой:
     * <pre>
     * [
     *  'message'    => string                 текст сообщения
     *  'type'       => const  | self::UNTYPED тип лога, см. константы этого класса
     *  'source'     => string | ''            источник сообщения
     *  'notify'     => bool | FALSE           флаг "Нужно оповещение по почте"
     *  'file_force' => bool | FALSE           сообщение писать в файл, независимо от настройки.
     * ]
     * </pre>

     * @param string|array $data
     * @return void
     * @throws \LogicException
     */
    public function add($data);

    /**
     * Запись типизированного сообщения
     * @param string $message текст сообщения
     * @param string $type    тип лога, см. константы этого класса
     * @return void
     */
    public function addTyped(string $message, string $type);
}

/**
 * Интерфейс среды окружения сайта
 */
interface IDetectEnvironment
{
    /**
     * Домен. Константы введены для устранения "magic string"
     */
    const
        D_LOCAL = 0,
        D_DEV = 1,
        D_STAGE = 2,
        D_PROD = 3,
        D_MOBILE = 4;

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
