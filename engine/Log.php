<?php
/**
 * Логирование.
 *
 * Класс предназначен для записи критической информации (ошибок) на больших отрезках времени.
 *
 * Логи могут писаться в БД или в файлы. Для записи в базу будет создана таблица `kira_log`, {@see Log::init()}, запись
 * в файлы ведется по маске "yyyymmdd_kira_log.csv", разделитель данных ";"
 *
 * Описывается группой настроек в конфиге приложения:
 * <pre>
 * 'log' => [
 *      'switch_on'   => true,      // включить логирование
 *      'store'       => \engine\Log::[STORE_IN_DB | STORE_IN_FILES], // тип хранителя логов
 *      'db_conf_key' => 'db',      // ключ конфига БД, если храним логи в базе
 *      'log_path'    => TEMP_PATH, // путь к каталогу, куда складывать файлы логов, если храним в файлах
 *      'timezone'    => '',        // часовой пояс для записи лога
 * ]
 * </pre>
 *
 * Логер может работать вообще без явной конфигурации. По умолчанию он включен, пишет в файлы в каталоге, заданном
 * в TEMP_PATH.
 *
 * Часовой пояс: сайт может работать в своей временной зоне, а логи нужно вести в зоне хостера хотя бы для того, чтобы
 * в случае сбоев вести диалог с тех.поддержкой про одно и тоже время. Список поясов тут
 * {@link http://php.net/manual/en/timezones.php}
 *
 * Каталог к файлам должен завершаться слешем. У веб-сервера должен быть доступ на запись в этот каталог. Если указать
 * пустое значение для 'log_path', тогда логирование в файлы будет отключено.
 *
 * Даже при хранении логов в базе рекомендуется задать каталог для лог-файлов. В случае сбоя подключения к БД логер
 * попытается писать в файлы. Если сбоит сохранение в файлы, будет отправлено письмо админу. Если не задан даже
 * админский email, тогда всё - /dev/nul.
 */

namespace engine;

use engine\net\Request,
    engine\db\Model,
    engine\utils\Mailer,
    engine\html\Render;

class Log
{
    // Типы логов
    const
        ENGINE = 'engine',
        DB_CONNECT = 'DB connection',
        DB_QUERY = 'DB query',
        EXCEPTION = 'exception',
        HTTP_ERR = 'HTTP error', // например, 404, 403 можно логировать
        PHP = 'PHP',             // ошибки PHP. Не уверен, что это целесообразно. Возможно удалю.
        UNTYPED = 'untyped';

    // Хранение лога. В случае сбоя переключаемся на вышестоящий. При 0 - только письмо админу.
    const
        STORE_ERROR = 0,
        STORE_IN_DB = 1,
        STORE_IN_FILES = 2;

    /** @var array конфигурация логера */
    private $_conf;

    /**
     * Конструктор.
     *
     * Определяем конфигурацию логера. Если в приложении конфига нет, принимаем все по умолчанию. Если требуется лог
     * в БД, проверяем наличие конфигурации подключения к базе. Если такой конфиг уточнил юзер, а его нет - проброс
     * исключения. Если не задан каталог, но требуется логирование в файлы - опять исключение.
     *
     * Прим.: конструктор не проверяет возможность записи лога по указанным настройкам. Это отслеживается
     * непосредственно при записи через возникающие ошибки.
     */
    public function __construct()
    {
        $conf = array_merge(
            [
                'switch_on' => true,
                'store'     => self::STORE_IN_FILES,
                //'db_conf_key' => 'db',
                'log_path'  => TEMP_PATH,
                'timezone'  => '',
            ],
            App::conf('log')
        );

        $conf['_mail'] = App::conf('admin_mail');

        if ($conf['store'] == self::STORE_IN_DB && !App::conf($conf['db_conf_key'], false)) {
            throw new \LogicException("Ошибка конфигурации логера: указан 'db_conf_key' к несуществующей настройке.\n"
                . 'Лог в БД невозможен');
        }

        if ($conf['store'] == self::STORE_IN_FILES && !$conf['log_path']) {
            throw new \LogicException("Ошибка конфигурации логера: не задан каталог ('log_path') для лог-файлов.\n"
                . 'Логирование в файлы невозможно.');
        }

         $this->_conf = $conf;
    }

    /**
     * Добавление записи в лог.
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
     * Уведомление на почту отсылается, если ящик указан в конфиге приложения, 'admin_mail'.
     *
     * 'file_force' = TRUE может пригодиться, для логирования ошибок типа 'DB'. Например, есть вероятность, что
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

        if (!$conf['switch_on'] || $conf['store'] == self::STORE_ERROR) {
            return;
        }

        $default = [
            'message'    => '',
            'type'       => self::UNTYPED,
            'source'     => '',
            'notify'     => FALSE,
            'file_force' => FALSE,
        ];

        if (is_array($data)) {
            $data = array_merge($default, $data);
        } else {
            $default['message'] = $data;
            $data = $default;
        }

        if (!$data['message']) {
            throw new \LogicException('Нет сообщения для записи в лог.');
        }

        if ($conf['timezone']) {
            $timezone = date_default_timezone_get();
            date_default_timezone_set($conf['timezone']);
        }
        $ts = new \DateTime();
        $logIt = [
            'type'     => $data['type'],
            'ts'       => $ts,
            'timezone' => $ts->format('\G\M\T P'),
            'userIP'   => Request::userIP(),
            'request'  => Request::absoluteURL(),
            'source'   => $data['source'],
            // Убираем tab-отступы
            'message'  => str_replace(chr(9), '', $data['message']),
        ];
        if ($conf['timezone']) {
            date_default_timezone_set($timezone);
            unset($timezone);
        }

        // Заранее готовимся к сбою. Так избежим циклических вызовов, когда классы DB попытаются логировать свои ошибки
        // в базу, которая уже лежит.
        $store = $this->_conf['store'];
        $result = false;
        if (!$data['file_force'] && $store == self::STORE_IN_DB) {
            $this->_conf['store'] = self::STORE_IN_FILES;
            if ($result = $this->_writeToDb($logIt)) {
                $this->_conf['store'] = $store;
            }
        }

        /*
        Если использовать закомменченное условие, тогда при двух сбоях подряд (ни в базу, ни в файлы) придет всего одно
        сообщение на мыло. НО! В нем будет инфа только об этих сбоях. С текущим условием писем будет два: первое о сбоях
        в логере, второе - собственно то сообщение, которое не удалось никуда записать.
        */
        //if (!$result && $store == self::STORE_IN_FILES) {

        if (!$result) {
            $this->_conf['store'] = self::STORE_ERROR;
            if ($this->_writeToFile($logIt)) {
                $this->_conf['store'] = $store;
            } else {
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
    public function addTyped($message, $type)
    {
        $this->add(compact('message', 'type'));
    }

    /**
     * Запись сообщения в базу.
     *
     * Ограничения на длину строк:
     * <ul>
     * <li>'message' можно считать неограниченным, тип поля TEXT (64 Кб)</li>
     * <li>'type' 20 символов. Обрезается в конце</li>
     * <li>'request' 255 символов, обрезается в конце, дописывается троеточие</li>
     * <li>'userIP' IPv4, следовательно 15 символов</li>
     * <li>'source' 100 символов, обрезается с начала строки, приписывается троеточие</li>
     * </ul>
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

            $request = $logIt['request'];
            if (mb_strlen($request) > 255) {
                $request = mb_substr($request, 0, 252) . '...';
            }

            $source = $logIt['source'];
            if (($len = mb_strlen($source)) > 100) {
                $source = '...' . mb_substr($source, $len - 97);
            }

            $data = [
                $logIt['ts']->format('Y-m-d H:i:s'),
                $logIt['timezone'],
                $logIt['type'],
                $logIt['message'],
                $logIt['userIP'],
                $request,
                $source,
            ];

            $result = (bool)(new Model($this->_conf['db_conf_key']))->query(['q' => $sql, 'p' => $data]);
        } catch (\Exception $e) {
            $logIt['message'] .= "\n\nДополнительно. Не удалось записать это сообщение в лог БД, причина: "
                . $e->getMessage();
            $result = false;
        }

        return $result;
    }

    /**
     * Запись сообщения в лог-файл.
     *
     * Тут важно поймать ошибки PHP и сообщить о них админу, когда error_reporting = 0. В этом случае добавляем свой
     * обработчик ошибок. {@see http://stackoverflow.com/questions/1241728/can-i-try-catch-a-warning}
     *
     * TODO возможна смена прав доступа к файлу, если его отредактировать под Kate. После этого логер падает. Нужно
     * придумать, как БЫСТРО и красиво управлять правами доступа.
     *
     * @param array &$logIt
     * @return bool
     */
    private function _writeToFile(&$logIt)
    {
        if ($customHandler = (error_reporting() == 0)) {
            set_error_handler(function ($errno, $errstr, $errfile, $errline) {
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            });
        }

        try {
            if (!$logPath = $this->_conf['log_path']) {
                throw new \ErrorException('Не задан каталог (\'log_path\') для лог-файлов.');
            }

            $fn = $logPath . $logIt['ts']->format('Ymd') . '_kira_log.csv';

            if ($file = fopen($fn, 'a')) {
                $result = (bool)fputcsv(
                    $file,
                    [
                        $logIt['type'],
                        $logIt['ts']->format('H:i:s'),
                        $logIt['timezone'],
                        $logIt['message'],
                        $logIt['source'],
                        $logIt['userIP'],
                        $logIt['request'],
                    ],
                    ';'
                );
                fclose($file);
            } else {
                $result = false;
            }
        } catch (\ErrorException $e) {
            $logIt['message'] .= "\n\nДополнительно. Не удалось записать это сообщение в файл лога, причина: "
                . $e->getMessage();
            $result = false;
        }

        if ($customHandler) {
            restore_error_handler();
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
        if (!$mailTo = $this->_conf['_mail']) {
            $this->addTyped('Не задан email админа, не могу отправить сообщение от логера.', self::ENGINE);
            return;
        }

        $domain = Env::domainName();
        $date = $logIt['ts']->format('Y/m/d H:i:s P');

        $letters['txt'] = "Сообщение от логера\n\n{$logIt['message']}"
            . "\n\nТип: {$logIt['type']}\nИсточник: {$logIt['source']}"
            . "\n\nURL запроса:{$logIt['request']}\nIP юзера: {$logIt['userIP']}\n\n$date (c) $domain";

        $vars = [
            'message' => nl2br($logIt['message']),
            'type'    => $logIt['type'],
            'source'  => $logIt['source'],
            'request' => $logIt['request'],
            'userIP'  => $logIt['userIP'],
            'date'    => $date,
            'homeURL' => Env::indexPage(),
            'domain'  => $domain,
        ];

        $letters['html'] = Render::make('log_letter.htm', $vars);

        $from = App::conf('noreply_mail') ?: "noreply@$domain";
        if (!Mailer::complex($from, $mailTo, "Сообщение от логера сайта $domain", $letters)) {
            $this->addTyped('Не удалось отправить сообщение от логера.', self::ENGINE);
        }
    }

    /**
     * Отправить логи на указанный адрес.
     *
     * Выбирает из логов данные от указанной даты включительно (unix timestamp) до текущего момента. Если заданы типы
     * лога, они учитываются. Получателей может быть несколько, указывать через запятую.
     *
     * Можно получить детальный отчет - копия логов за указанный период, или только сводку - по каждому типу количество
     * сообщений. При этом типы с нулевым количеством пропускаются.
     *
     * Предполагается работа в связке с cron через внешний управляющий php-скрипт. В нем описываем параметры и вызываем
     * этот метод.
     *
     * @param string $mails    email получателя(ей)
     * @param int    $from_ts  временная метка (unix timestamp), от которой до текущего момент выбрать логи
     * @param bool   $detailed детальный отчет или только сводка
     * @param array  $types    типы логов, см. константы этого класса
     */
    public static function report($mails, $from_ts, $detailed, $types = [])
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
     * у веб-сервера должны быть права на такую операцию.
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
Нужно предлагать юзеру выбор движка таблицы MyISAM | InnoDB. <radio-button>
По умолчанию - MyISAM. Она значительно шустрее на select-ах и insert-ах. И логам не требуется поддержка транзакций.
Явная выгода.

USE database ...

DROP TABLE IF EXISTS `kira_log`;

CREATE TABLE `kira_log` (
  `id` int(12) unsigned NOT NULL AUTO_INCREMENT,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата/время события',
  `timezone` char(10) NOT NULL COMMENT 'Часовой пояс, которому соответствует указанное время события',
  `logType` varchar(20) NOT NULL COMMENT 'Тип сообщения',
  `message` text NOT NULL COMMENT 'Сообщение',
  `userIP` char(15) DEFAULT '' COMMENT 'IPv4, адрес юзера, когда удалось его определить',
  `request` varchar(255) DEFAULT '' COMMENT 'URL запроса, в ходе обработки которого пишем лог',
  `source` varchar(100) DEFAULT '' COMMENT 'источник сообщения (функция, скрипт, какая-то пометка кодера)',
  PRIMARY KEY (`id`),
  KEY `ts` (`ts`),
  KEY `logType` (`logType`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
        */
    }
}
