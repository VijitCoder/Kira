<?php
namespace engine;

use engine\net\Request,
    engine\db\DbModel,
    engine\utils\Mailer,
    engine\html\Render;

/**
 * Логирование.
 *
 * Класс предназначен для записи критической информации (ошибок) на больших отрезках времени.
 *
 * Логи могут писаться в БД или в файлы. Для записи в базу будет создана таблица `kira_log`, {@see Log::init()}, запись
 * в файлы ведется по маске "yyyymmdd_kira_log.csv", разделитель данных ";"
 *
 * Поведение логера описывается группой настроек в конфиге приложения:
 * <pre>
 * 'log' => [
 *      'switch_on'    => true,       // включить логирование
 *      'store'        => \engine\Log::[STORE_IN_DB | STORE_IN_FILES], // тип хранителя логов
 *      'db_conf_key'  => 'db',       // ключ конфига БД (значение по умолчанию), если храним логи в базе
 *      'table_name'   => 'kira_log', // таблица лога (значение по умолчанию) при записи в БД
 *      'log_path'     => TEMP_PATH,  // путь к каталогу, куда складывать файлы логов, если храним в файлах
 *      'php_timezone' => '',         // часовой пояс для записи лога
 * ]
 * </pre>
 *
 * Логер может работать вообще без явной конфигурации. По умолчанию он включен, пишет в файлы в каталог, заданный
 * в TEMP_PATH.
 *
 * Часовой пояс: сайт может работать в одном поясе, а логи можно писать в поясе хостера хотя бы для того, чтобы в случае
 * сбоя вести диалог с тех.поддержкой про одно и тоже время. Список поясов тут
 * {@link http://php.net/manual/en/timezones.php}
 *
 * Каталог к файлам должен вести от корня диска и завершаться слешем. У веб-сервера должен быть доступ на запись в этот
 * каталог. Если указать пустое значение для 'log_path', тогда логирование в файлы будет отключено.
 *
 * Даже при хранении логов в базе рекомендуется задать каталог для лог-файлов. В случае сбоя подключения к БД логер
 * попытается писать в файлы. Если сбоит сохранение в файлы, будет отправлено письмо админу. Если не задан даже
 * админский email, тогда всё - /dev/nul.
 */
class Log
{
    // Типы логов
    const
        ENGINE = 'engine',
        DB_CONNECT = 'DB connection',
        DB_QUERY = 'DB query',
        EXCEPTION = 'exception',
        HTTP_ERROR = 'HTTP error', // например, 404, 403 можно логировать
        INFO = 'information',
        UNTYPED = 'untyped';

    // Хранение лога. В случае сбоя переключаемся на вышестоящий. При 0 - только письмо админу.
    const
        STORE_ERROR = 0,
        STORE_IN_FILES = 1,
        STORE_IN_DB = 2;

    /** @var array конфигурация логера */
    private $conf;

    /**
     * Внутреннее представление данных для логирования. Чтобы между функциями не передавать этот массив, оформил в поле
     * класса.
     * @var array
     */
    private $logIt;

    /**
     * Конструктор.
     *
     * Определяем конфигурацию логера. Если в приложении конфига нет, принимаем все по умолчанию. Если требуется лог
     * в БД, проверяем наличие конфигурации подключения к базе. Если его нет - проброс исключения. Если не задан каталог,
     * но требуется логирование в файлы - опять исключение.
     *
     * Прим.: конструктор не проверяет возможность записи лога по указанным настройкам. Это отслеживается
     * непосредственно при записи через возникающие ошибки.
     *
     * @throws \LogicException
     */
    public function __construct()
    {
        $conf = array_merge(
            [
                'switch_on'    => true,
                'store'        => self::STORE_IN_FILES,
                'db_conf_key' => 'db',
                'log_path'     => TEMP_PATH,
                'php_timezone' => '',
                'table_name'   => 'kira_log',
            ],
            App::conf('log', false) ? : []
        );

        $conf['_mail'] = App::conf('admin_mail', false);

        if ($conf['store'] == self::STORE_IN_DB && !App::conf($conf['db_conf_key'], false)) {
            throw new \LogicException('Ошибка конфигурации логера: указан "db_conf_key" к несуществующей настройке.'
                . PHP_EOL . 'Лог в БД невозможен');
        }

        if ($conf['store'] == self::STORE_IN_FILES && !$conf['log_path']) {
            throw new \LogicException('Ошибка конфигурации логера: не задан каталог ("log_path") для лог-файлов.'
                . PHP_EOL . 'Логирование в файлы невозможно.');
        }

        $this->conf = $conf;
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
     * Пишем письмо админу, если установлен флаг "notify" или произойдет сбой логирования в файлы. Ящик получателя
     * должен быть указан в конфиге приложения, 'admin_mail'.
     *
     * 'file_force' = TRUE движок логирует ошибки подключения в базе. Всегда пишет их в файлы, для принудительного
     * переключения служит этот флаг. Можно использовать его и в своих целях. Кстати, ошибки SQL-запросов тоже
     * логируются движком.
     *
     * @param string|array $data
     * @return void
     * @throws \LogicException
     */
    public function add($data)
    {
        $conf = $this->conf;

        if (!$conf['switch_on'] || $conf['store'] == self::STORE_ERROR) {
            return;
        }

        $default = [
            'message'    => '',
            'type'       => self::UNTYPED,
            'source'     => '',
            'notify'     => false,
            'file_force' => false,
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

        $this->prepareLogData($data);

        // Заранее готовимся к сбою. Так избежим циклических вызовов, когда классы DB попытаются логировать свои ошибки
        // в базу, которая уже лежит.
        $store = $this->conf['store'];
        $result = false;
        if (!$data['file_force'] && $store == self::STORE_IN_DB) {
            $this->conf['store'] = self::STORE_IN_FILES;
            if ($result = $this->_writeToDb()) {
                $this->conf['store'] = $store;
            }
        }

        /*
        Если использовать закомменченное условие, тогда при двух сбоях подряд (ни в базу, ни в файлы) придет всего одно
        сообщение на мыло. НО! В нем будет инфа только об этих сбоях. С текущим условием писем будет два: первое о сбоях
        в логере, второе - собственно то сообщение, которое не удалось никуда записать. Это работает, если заранее
        готовимся к сбою в файлах.
        */
        //if (!$result && $store == self::STORE_IN_FILES) {

        if (!$result) {
            $this->conf['store'] = self::STORE_ERROR;
            if ($this->writeToFile()) {
                $this->conf['store'] = $store;
            } else {
                $data['notify'] = true;
            }
        }

        if ($data['notify']) {
            $this->mailToAdmin();
        }
    }

    /**
     * Готовим данные для логирования.
     *
     * Кроме сообщения, переданного клиентским кодом, добавляем полезную инфу: дата/время, часовой пояс, IP юзера,
     * URL запроса.
     *
     * @param array $data исходные данные
     * @return void
     */
    private function prepareLogData(&$data)
    {
        $ts = $this->conf['php_timezone']
            ? new \DateTime(null, new \DateTimeZone($this->conf['php_timezone']))
            : new \DateTime();

        $this->logIt = [
            'type'     => $data['type'],
            'ts'       => $ts,
            'timezone' => $ts->format('\G\M\T P'),
            'userIP'   => Request::userIP(),
            'request'  => Request::absoluteURL(),
            'source'   => $data['source'],
            // Убираем tab-отступы
            'message'  => str_replace(chr(9), '', $data['message']),
        ];
    }

    /**
     * Обертка: запись в лог с указанием типа.
     *
     * Как оказалось, из всех параметров обычной записи в лог наиболее актуальным является тип лога, остальное можно
     * принять по умолчанию. Для сокращенного вызова записи в лог служит данная обертка.
     *
     * @param string $message текст сообщения
     * @param string $type    тип лога, см. константы этого класса
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
     * @return bool
     * @throws \Exception
     */
    private function _writeToDb()
    {
        $logIt = $this->logIt;
        try {
            $table = $this->conf['table_name'];
            $sql =
                "INSERT INTO `{$table}` (`ts`,`timezone`,`logType`,`message`,`userIP`,`request`,`source`)
                VALUES (?,?,?,?,?,?,?)";

            $request = $logIt['request'];
            if (mb_strlen($request) > 255) {
                $request = mb_substr($request, 0, 252) . '...';
            }

            $source = $logIt['source'];
            if (($len = mb_strlen($source)) > 100) {
                $source = '...' . mb_substr($source, $len - 97);
            }

            $params = [
                $logIt['ts']->format('Y-m-d H:i:s'),
                $logIt['timezone'],
                $logIt['type'],
                $logIt['message'],
                $logIt['userIP'],
                $request,
                $source,
            ];

            $result = (bool)(new DbModel($this->conf['db_conf_key']))
                ->query($sql, $params)
                ->effect();

        } catch (\Exception $e) {
            $logIt['message'] .= PHP_EOL . PHP_EOL
                . 'Дополнительно. Не удалось записать это сообщение в лог БД, причина: ' . $e->getMessage();
            $result = false;
        }

        return $result;
    }

    /**
     * Запись сообщения в лог-файл.
     *
     * Тут важно поймать ошибки PHP и сообщить о них админу, когда они отключены через error_reporting. В этом случае
     * добавляем свой обработчик ошибок. Рассматриваем только ситацию error_reporting = 0. Только в этом случае свой
     * перехватчик.
     *
     * TODO редактор Kate меняет владельца файла при редактировании! Не разобрался, почему он вообще такое может, файл
     * имел права 0644, владелец "www-data". После смены владельца логер падает, т.к. у веб-сервера нет больше прав
     * на запись. Как-то это нужно разобрать и пофиксить.
     *
     * @return bool
     * @throws \LogicException
     */
    private function writeToFile()
    {
        $logIt = $this->logIt;

        if ($customHandler = (error_reporting() == 0)) {
            set_error_handler(function ($errno, $errstr, $errfile, $errline) {
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            });
        }

        try {
            if (!$logPath = $this->conf['log_path']) {
                throw new \ErrorException('Не задан каталог ("log_path") для лог-файлов.');
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
                throw new \ErrorException('Не могу писать в файл ' . $fn);
            }
        } catch (\ErrorException $e) {
            $logIt['message'] .= PHP_EOL . PHP_EOL
                . 'Дополнительно. Не удалось записать это сообщение в файл лога, причина: ' . $e->getMessage();
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
     * @return void
     */
    private function mailToAdmin()
    {
        $logIt = $this->logIt;

        if (!$mailTo = $this->conf['_mail']) {
            $this->addTyped('Не задан email админа, не могу отправить сообщение от логера.', self::ENGINE);
            return;
        }

        $domain = Env::domainName();
        $date = $logIt['ts']->format('Y/m/d H:i:s P');

        $rn = PHP_EOL;
        $letters['text'] =
            'Сообщение от логера' . $rn
            . $rn
            . $logIt['message'] . $rn
            . $rn
            . "Тип: {$logIt['type']}$rn"
            . "Источник: {$logIt['source']}" . $rn
            . $rn
            . "URL запроса:{$logIt['request']}" . $rn
            . "IP юзера: {$logIt['userIP']}" . $rn
            . $rn
            . "$date (c) $domain";

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

        $letters['html'] = Render::fetch('log_letter.htm', $vars);

        $from = App::conf('noreply_mail') ?: "noreply@$domain";
        if (!Mailer::complex($from, $mailTo, "Сообщение от логера сайта $domain", $letters)) {
            $this->addTyped('Не удалось отправить сообщение от логера.', self::ENGINE);
        }
    }

    /**
     * @DEPRECATED
     *
     * Геттер данных из последней процедуры логирования.
     *
     * Служебная функция, для отладки функционала. Возможно будет удалена в дальнейшем. Возвращает внутреннее
     * представление данных для логирования, подготовленных в последнем обращении к логеру.
     *
     * @return array
     */
    public function getLogData()
    {
        return $this->logIt;
    }
}
