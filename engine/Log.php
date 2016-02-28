<?php
/**
 * Логирование.
 *
 * Логи могут писаться в БД или в файлы. Для записи в базу будет создана таблица `kira_log`, {@see Log::init()}, запись
 * в файлы ведется по маске "yyyymmdd_kira_log.csv", разделитель данных ";"
 *
 * Описывается группой настроек в конфиге приложения:
 * log => [
 *      'store'       => \engine\Log::[STORE_IN_DB | STORE_IN_FILES], // тип хранителя логов
 *      'db_conf_key' => 'db',     // ключ конфига БД, если храним логи в базе
 *      'file_path'   => APP_TEMP, // путь к каталогу, куда складывать файлы логов, если храним в файлах
 *      'timezone'    => null,     // временная зона для записи лога
 * ]
 *
 * Логер может работать без настроек вообще. Если нет ключей к базе, пишет в файлы, иначе в БД. Любое сообщение можно
 * отправить на ящик админу, см. конфиг приложения - 'adminMail' и метод {@see Log::add()}
 *
 * Временная зона: сайт может работать в своей временной зоне, а логи нужно вести в зоне хостера хотя бы для того, чтобы
 * в случае сбоев вести диалог с тех.поддержкой про одно и тоже время.
 *
 * Даже при хранении логов в базе рекомендуется организовать <b>каталог</b> для файлов. В случае сбоя подключения к БД
 * логер попытается писать в файлы. Если сбоит сохранение в файлы, будет отправлено письмо админу, один раз на каждый
 * реквест браузера (если в сборке ответа есть логирование). Если не задан даже админский email, тогда всё - /dev/nul.
 */

namespace engine;

use engine\net\Request,
    engine\db\Model;

class Log
{
    // Типы логов
    const
        ENGINE = 'engine',
        INFO = 'info',
        TRACE = 'trace',
        DB = 'DB',
        EXCEPTION = 'exception',
        HTTP_ERR = 'HTTP error', // например, 404, 403 можно логировать
        PHP = 'PHP',             // ошибки PHP. Не уверен, что это целесообразно. Возможно удалю.
        UNTYPED = 'untyped';

    const
        STORE_ERROR = 0,    // когда полностью сбоит логирование
        STORE_IN_DB = 1,
        STORE_IN_FILES = 2;

    /** @var array конфигурация логера */
    private $_conf;

    /**
     * Конструктор.
     *
     * Определяем конфигурацию логера. Если в приложении конфига нет, принимаем все по умолчанию. Если требуется лог
     * в БД, проверяем наличие конфигурации подключения к базе. Если такой конфиг уточнил юзер, а его нет - это сбой,
     * переключаемся на лог в файлы и сразу же пишем в него ошибку настройки логера.
     *
     * Прим.: конструктор не проверяет возможность записи лога по указанным настройкам. Это отслеживается
     * непосредственно при записи.
     */
    public function __construct()
    {
        $userConf = App::conf('log');
        $conf = array_merge(
            [
                'store'       => self::STORE_IN_DB,
                'db_conf_key' => 'db',
                'file_path'   => APP_TEMP,
                'timezone'    => null,
            ],
            $userConf
        );

        if ($conf['store'] == self::STORE_IN_DB) {
            if (!App::conf($conf['db_conf_key'], false)) {
                $conf['store'] = self::STORE_IN_FILES;
                if (isset($userConf['db_conf_key'])) {
                    $this->addTyped('Ошибка конфигурации логера: указан "db_conf_key" к несуществующей настройке. '
                        . 'Лог в БД невозможен', self::ENGINE);
                }
            }
        }

        $conf['_mail'] = App::conf('adminMail');

        $this->_conf = $conf;
    }

    /**
     * Добавление записи в лог.
     *
     * Ожидаем либо строку с сообщением либо массив, содержащий сообщение и другие данные. Полный формат массива такой:
     * <pre>
     * [
     *  'msg'  => string                 текст сообщения
     *  'type' => const  | self::UNTYPED тип лога, см. константы этого класса
     *  'src'  => string | ''            источник сообщения
     *  'notify' => bool | FALSE         флаг "Нужно оповещение по почте"
     *  'fileForce' => bool | FALSE      сообщение писать в файл, независимо от настройки.
     * ]
     * </pre>
     *
     * Логика метода такая: если устраивают значения массива по умолчанию - тогда можно передать вместо него строковый
     * параметр сообщения и всё. Иначе через ключи массива уточняем, что поменять в поведении записи лога.
     *
     * Тип лога необязательно указывать константой класса. Легко можно ввести свой тип и указывать его. Это же строка,
     * просто пишем, что нужно, в качестве типа лога.
     *
     * Источник сообщения ('src')- любой текст, например, имя метода или вообще произвольное описание.
     * См. так же волшебные константы PHP {@see http://php.net/manual/ru/language.constants.predefined.php}
     *
     * Уведомление на почту отсылается, если ящик указан в конфиге приложения, 'adminMail'.
     *
     * 'fileForce' = TRUE может пригодиться, для логирования ошибок типа 'DB'. Например, есть вероятность, что
     * MySQL-сервер у вас сбоит. Тогда часть ошибок такого типа может попасть в таблицу, а часть - нет, из-за сбоя.
     * Чтобы потом не собирать из разных мест картину сбоя в целом, можно всегда логировать сообщения типа 'DB'
     * принудительно в файлы. Экспериментальная настройка, возможно будет удалена в будущем.
     *
     * @param string|array $data
     * @return void
     */
    public function add($data)
    {
        $conf = $this->_conf;

        if ($conf['store'] == self::STORE_ERROR) {
            return;
        }

        $default = [
            'msg'       => '',
            'type'      => self::UNTYPED,
            'src'       => '',
            'notify'    => FALSE,
            'fileForce' => FALSE,
        ];

        if (is_array($data)) {
            $data = array_merge($default, $data);
        } else {
            $default['msg'] = $data;
            $data = $default;
        }

        if (!$data['msg']) {
            return;
        }

        if ($conf['timezone']) {
            $timezone = date_default_timezone_get();
            date_default_timezone_set($conf['timezone']);
        }
        $ts = time();
        $logIt = [
            'type'    => $data['type'],
            'date'    => date('d.m.Y', $ts),
            'time'    => date('H:i:s', $ts),
            'GMT'     => date('\G\M\T O', $ts),
            'IP'      => Request::userIP(),
            'request' => Request::absoluteURL(),
            'source'  => $data['src'],
            'msg'     => $data['msg'],
        ];
        if ($conf['timezone']) {
            date_default_timezone_set($timezone);
            unset($timezone);
        }

        $result = false;

        if (!$data['fileForce'] && $conf['store'] == self::STORE_IN_DB) {
            if (!$result = $this->_write_in_db($logIt)) {
                $conf['store'] = self::STORE_IN_FILES;
                $this->addTyped('Ошибка записи лога в базу', self::ENGINE);
            }
        }

        if (!$result) {
            if (!$this->_write_in_file($logIt)) {
                $conf['store'] = self::STORE_ERROR;
                $logIt['msg'] = '<p><b>Сбой: не могу записать это сообщение в лог.</b></p>' . $logIt['msg'];
                $data['notify'] = true;
            }
        }

        if ($data['notify']) {
            $this->_mailToAdmin($logIt);
        }
    }

    /**
     * Обертка: запись в лог с указанием типа.
     *
     * Как оказалось, из всех параметров обычной записи в лог наиболее актуальным является тип лога, остальное можно
     * принять по умолчанию. Для сокращенного вызова записи в лог служит данная обертка.
     *
     * @param string $msg  текст сообщения
     * @param string $type тип лога, см. константы этого класса
     * @return void
     */
    public function addTyped($msg, $type)
    {
        // TODO реализация
    }

    /**
     * Запись сообщения в базу
     *
     * @param array $data
     * @return bool
     */
    private function _write_in_db($data)
    {
        return true;
    }

    /**
     * Запись сообщения в лог-файл
     *
     * @param array $data
     * @return bool
     */
    private function _write_in_file($data)
    {
        // доступ к файлу 664, чтоб обычный юзер не мог править логи. Только читать и удалять.

        //@TODO возможна смена прав доступа к файлу, если его отредактировать под Kate. После этого
        //логер падает. Нужно придумать, как БЫСТРО и красиво управлять правами доступа.
        //Не удалось создать файл - пишем админу, не зависимо от предыдущего состояния флага.
        //if (!$file = @fopen("{$way['log']}$log.csv", 'a')) {
        //    $needMailing = true;
        //}
        return true;
    }

    private function _mailToAdmin($data)
    {
        if (!$mail = $this->conf['_mail']) {
            $this->addTyped('Не задан email админа, не могу отправить сообщение от логера.', self::ENGINE);
            return;
        }
        // TODO html-письмо со всеми данными лог-сообщения
    }

    /**
     * Отправить лог на указанный адрес.
     *
     * Выбирает из логов данные от указанной даты включительно (unix timestamp) до текущего момента. Если заданы типы
     * лога, они учитываются. Получателей может быть несколько, указывать через запятую.
     *
     * Предполагается работа в связке с cron через внешний управляющий php-скрипт. В нем описываем параметры и вызываем
     * этот метод.
     *
     * @param string $to    email получателя(ей)
     * @param int    $from  временная метка (unix timestamp), от которой до текущего момент выбрать логи
     * @param array  $types типы логов, см. константы этого класса
     */
    public static function report($to, $from, $types = [])
    {
        // TODO реализация
    }

    /**
     * Удаление логов.
     *
     * Все логи с начала и до указанной временной метки включительно (unix timestamp). Если метка не задана, тогда
     * вообще все логи.
     *
     * Предполагается работа в связке с cron через внешний управляющий php-скрипт. В нем описываем параметры и вызываем
     * этот метод.
     *
     * @param int $till временная метка (unix timestamp), до которой удалить логи.
     */
    public static function delete($till = null)
    {
        // TODO реализация
    }

    /**
     * Инициализация окружения логера.
     *
     * В зависимости от настроек, создает базу и таблицу и/или каталог для сохранения логов в файлы. Если среда уже
     * создана, пропускает ненужные шаги. Исходим из того, что база будет в СУБД MySQL, запросы написаны для неё.
     *
     * Предполагается одноразовый вызов метода, например через мастер создания приложения.
     */
    public static function init()
    {
        // TODO реализация
        // При логировании в базу нужно проверить наличие БД и создать ее, если требуется.

        // Работаем в заданной из index.php кодировке. Внимание! Нет прямого совпадения в названиях кодировок у PHP и
        // MySQL. А если используется вообще другая СУБД, то результат еще более неопределенный. Стоит ли тут
        // заморачиваться?
        //$codepage = mb_internal_encoding();
    }
}
