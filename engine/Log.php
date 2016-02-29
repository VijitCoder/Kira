<?php
/**
 * Логирование.
 *
 * Логи могут писаться в БД или в файлы. Для записи в базу будет создана таблица `kira_log`, {@see Log::init()}, запись
 * в файлы ведется по маске "yyyymmdd_kira_log.csv", разделитель данных ";"
 *
 * Описывается группой настроек в конфиге приложения:
 * <pre>
 * log => [
 *      'store'       => \engine\Log::[STORE_IN_DB | STORE_IN_FILES], // тип хранителя логов
 *      'db_conf_key' => 'db',      // ключ конфига БД, если храним логи в базе
 *      'log_path'    => TEMP_PATH, // путь к каталогу, куда складывать файлы логов, если храним в файлах
 *      'timezone'    => '',        // временная зона для записи лога
 * ]
 * </pre>
 *
 * Логер может работать без настроек вообще. Если нет ключей к базе, пишет в файлы, иначе в БД. Любое сообщение можно
 * отправить на ящик админу, см. конфиг приложения - 'adminMail' и метод {@see Log::add()}
 *
 * Временная зона: сайт может работать в своей временной зоне, а логи нужно вести в зоне хостера хотя бы для того, чтобы
 * в случае сбоев вести диалог с тех.поддержкой про одно и тоже время. Список зон тут
 * {@link http://php.net/manual/en/timezones.php}
 *
 * Каталог к файлам должен завершаться слешем. У веб-сервера должен быть доступ на запись в этот каталог. Если указать
 * пустое значение для 'log_path', тогда логирование в файлы будет отключено.
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
                'log_path'    => TEMP_PATH,
                'timezone'    => '',
            ],
            $userConf
        );

        $conf['_mail'] = App::conf('adminMail');

        $errors = [];
        if ($conf['store'] == self::STORE_IN_DB && !App::conf($conf['db_conf_key'], false)) {
            $conf['store'] = self::STORE_IN_FILES;
            if (isset($userConf['db_conf_key'])) {
                $errors[] = 'Ошибка конфигурации логера: указан "db_conf_key" к несуществующей настройке. '
                    . 'Лог в БД невозможен';
            }
        }

        if ($conf['store'] == self::STORE_IN_FILES && !$conf['log_path']) {
            $conf['store'] = self::STORE_ERROR;
            $errors[] = 'Ошибка конфигурации логера: не задан каталог для лога в файлы. Логирование невозможно.';
        }

        if ($errors) {
            $this->addTyped(implode("\n", $errors), self::ENGINE);
        }

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
        $ts = new DateTime();
        $logIt = [
            'type'     => $data['type'],
            'date'     => $ts->format('Ymd'),
            'time'     => $ts->format('H:i:s.u'),
            'timezone' => $ts->format('\G\M\T P'),
            'userIP'   => Request::userIP(),
            'request'  => Request::absoluteURL(),
            'source'   => $data['src'],
            // Убираем tab-отступы
            'msg'      => str_replace(chr(9), '', $data['msg']),
        ];
        if ($conf['timezone']) {
            date_default_timezone_set($timezone);
            unset($timezone);
        }

        $result = false;

        if (!$data['fileForce'] && $conf['store'] == self::STORE_IN_DB) {
            if (!$result = $this->_writeToDb($logIt)) {
                $conf['store'] = self::STORE_IN_FILES;
                $this->addTyped('Ошибка записи лога в базу', self::ENGINE);
            }
        }

        if (!$result) {
            if (!$this->_writeInFile($logIt)) {
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
        $this->add(compact('msg', 'type'));
    }

    /**
     * Запись сообщения в базу.
     *
     * Время пишем с микросекундами. На этом значении построен первичный ключ таблицы. Поэтому в случае успешной записи
     * ждем 1мс, чтобы гарантировать новую метку времени для следующего сообщения (уникальный ключ).
     *
     * @param array $logIt
     * @return bool
     */
    private function _writeToDb(&$logIt)
    {
        try {
            $sql = '
                INSERT INTO `kira_log` (`ts`,`timezone`,`logType`,`message`,`userIP`,`request`,`source`)
                VALUES (?,?,?,?,?,?,?)';
            $data = [
                $logIt['date'] . ' ' . $logIt['time'],
                $logIt['timezone'],
                $logIt['logType'],
                $logIt['message'],
                $logIt['userIP'],
                $logIt['request'],
                $logIt['source'],
            ];

            $result = (bool)(new Model($this->_conf['db_conf_key']))->query(['q' => $sql, 'p' => $data]);
        } catch (\Exception $e) {
            $logIt['msg'] .= "\n\nДополнительно: не удалось записать это сообщение в лог БД. Ошибка:\n"
                . $e->getMessage();
            $result = false;
        }

        if ($result) {
            usleep(1);
        }

        return $result;
    }

    /**
     * Запись сообщения в лог-файл.
     *
     * @TODO возможна смена прав доступа к файлу, если его отредактировать под Kate. После этого логер падает. Нужно
     * придумать, как БЫСТРО и красиво управлять правами доступа.
     *
     * @param array &$logIt
     * @return bool
     */
    private function _writeInFile(&$logIt)
    {
        $fn = $this->_conf[file_path] . $logIt['date'] . '_kira_log.csv';

        $result = false;
        try {
            $file = fopen($fn, 'a');
            fputcsv($file, $logIt, ';');
            $result = true;
        } finally {
            if ($file) {
                fclose($file);
            }
        }

        return $result;
    }

    /**
     * Письмо админу с текущим сообщение лога.
     *
     * Пишем, если в параметрах сообщения установлен флаг "notify" или в случае сбоя логирования.
     *
     * @param array $logIt
     * @return void
     */
    private function _mailToAdmin(&$logIt)
    {
        if (!$mail = $this->conf['_mail']) {
            $this->addTyped('Не задан email админа, не могу отправить сообщение от логера.', self::ENGINE);
            return;
        }
        // TODO txt и html версии письма со всеми данными лог-сообщения
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
     * Предполагается одноразовый вызов метода, например через мастер создания приложения.
     *
     * В зависимости от настроек, создаем базу и таблицу и/или каталог для сохранения логов в файлы. Если среда уже
     * создана, пропускаем ненужные шаги. Исходим из того, что база под управлением СУБД MySQL, запросы написаны для неё.
     *
     * Для работы в БД нужны ключи юзера с правами создания базы и таблицы в базе. Для создания каталога логов
     * у веб-сервера должны быть права на это.
     */
    public static function init()
    {
        // TODO реализация
        // При логировании в базу нужно проверить наличие БД и создать ее, если требуется.

        // Работаем в заданной из index.php кодировке. Внимание! Нет прямого совпадения в названиях кодировок у PHP и
        // MySQL. А если используется вообще другая СУБД, то результат еще более неопределенный. Стоит ли тут
        // заморачиваться?
        //$codepage = mb_internal_encoding();


        // доступ к файлу 664, чтоб обычный юзер не мог править логи. Только читать и удалять.
        //if (mkdir($dirpath, 0777, true) && chmod($dirpath, 0664)) {...}

        /*
USE database ...

CREATE TABLE `kira_log` (
    `ts` TIMESTAMP NOT NULL COMMENT 'Дата/время события, с микросекундами',
    `timezone` CHAR(10) NOT NULL COMMENT 'Часовой пояс, которому соответствует указанное время события',
    `logType` VARCHAR(20) NOT NULL COMMENT 'Тип сообщения',
    `message` TEXT NOT NULL COMMENT 'Сообщение',
    `userIP` CHAR(15) NULL DEFAULT '' COMMENT 'IPv4, адрес юзера, когда удалось его определить',
    `request` VARCHAR(255) NULL DEFAULT '' COMMENT 'URL запроса, в ходе обработки которого пишем лог',
    `source` VARCHAR(100) NULL DEFAULT '' COMMENT 'источник сообщения (функция, скрипт, какая-то пометка кодера)',
    KEY `logType` (`logType`)
)
ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci;
        */
    }
}
