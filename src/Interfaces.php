<?php
/**
 * Интерфейсы. Все в одном месте. Скрипт загружается отдельно в autoloader.php, т.к. функция автозагрузчика его
 * не найдет в таком виде, а создавать кучу файлов под интерфейсы нехорошо.
 */

namespace kira;

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
