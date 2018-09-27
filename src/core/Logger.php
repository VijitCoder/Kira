<?php
namespace kira\core;

use kira\exceptions\DbException;
use kira\exceptions\LoggerException;
use kira\net\Request;
use kira\db\DbModel;
use kira\utils\Mailer;
use kira\html\Render;
use kira\utils\System;
use kira\web\Env;

/**
 * Логирование
 *
 * Класс предназначен для записи критической информации (ошибок) на больших отрезках времени.
 *
 * Для записи в базу нужна таблица `kira_log` (настройка). Запись в файлы ведется по маске "yyyymmdd_kira_log.csv",
 * разделитель данных ";"
 *
 * См. документацию, "Логер".
 */
class Logger extends AbstractLogger
{
    /**
     * @var array конфигурация логера
     */
    private $conf;

    /**
     * Внутриклассовое представление данных для логирования
     * @var array
     */
    private $logIt;

    /**
     * Определяем конфигурацию логера. Если в приложении конфига нет, принимаем все по умолчанию. Если требуется лог
     * в БД, проверяем наличие конфигурации подключения к базе. Если его нет - проброс исключения. Если не задан
     * каталог, но требуется логирование в файлы - опять исключение.
     *
     * Прим.: конструктор не проверяет возможность записи лога по указанным настройкам. Это отслеживается
     * непосредственно при записи через возникающие ошибки.
     *
     * @throws LoggerException
     */
    public function __construct()
    {
        $conf = array_merge(
            [
                'switch_on'   => true,
                'store'       => self::STORE_IN_FILES,
                'db_conf_key' => 'db',
                'log_path'    => KIRA_TEMP_PATH,
                'table_name'  => 'kira_log',
            ],
            App::conf('log', false) ?: []
        );

        $conf['_mail'] = App::conf('admin_mail', false);

        if ($conf['store'] == self::STORE_IN_DB && !App::conf($conf['db_conf_key'], false)) {
            throw new LoggerException(
                'Ошибка конфигурации логера: указан "db_conf_key" к несуществующей настройке.'
                . PHP_EOL . 'Лог в БД невозможен',
                LoggerException::LOGIC_ERROR
            );
        }

        if ($conf['store'] == self::STORE_IN_FILES && !$conf['log_path']) {
            throw new LoggerException(
                'Ошибка конфигурации логера: не задан каталог ("log_path") для лог-файлов.'
                . PHP_EOL . 'Логирование в файлы невозможно.',
                LoggerException::LOGIC_ERROR
            );
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
     *  'notify'     => bool | FALSE           флаг "Оповещение по почте", письмо на 'admin_mail' конфига приложения
     *  'file_force' => bool | FALSE           сообщение писать в файл, независимо от настройки.
     * ]
     * </pre>
     *
     * @param string|array $data
     * @return void
     * @throws LoggerException
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
            throw new LoggerException('Нет сообщения для записи в лог.', LoggerException::LOGIC_ERROR);
        }

        $this->prepareLogData($data);

        // Заранее готовимся к сбою. Так избежим циклических вызовов, когда классы DB попытаются логировать свои ошибки
        // в базу, которая уже лежит.
        $store = $this->conf['store'];
        $result = false;
        if (!$data['file_force'] && $store == self::STORE_IN_DB) {
            $this->conf['store'] = self::STORE_IN_FILES;
            if ($result = $this->writeToDb()) {
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
     * Кроме сообщения, переданного клиентским кодом, добавляем полезную инфу: дата/время, IP юзера, URL запроса.
     *
     * @param array $data исходные данные
     * @return void
     */
    private function prepareLogData($data)
    {
        $this->logIt = [
            'type'      => $data['type'],
            'createdAt' => new \DateTime,
            'userIP'    => Request::userIP(),
            'request'   => Request::absoluteURL(),
            'source'    => $data['source'],
            // Убираем tab-отступы
            'message'   => str_replace(chr(9), '', $data['message']),
        ];
    }

    /**
     * Обертка: запись в лог с указанием типа. Остальные параметры принимаем по умолчанию.
     * @param string $message текст сообщения
     * @param string $type    тип лога, см. константы этого класса
     * @return void
     */
    public function addTyped(string $message, string $type)
    {
        $this->add(compact('message', 'type'));
    }

    /**
     * Запись сообщения в базу
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
     */
    private function writeToDb()
    {
        try {
            $table = $this->conf['table_name'];
            $sql =
                "INSERT INTO `{$table}` SET
                    `created_at` = NOW(),
                    `log_type` = :type,
                    `message` = :message,
                    `user_ip` = :userIP,
                    `request` = :request,
                    `source` = :source";

            $params = $this->logIt;

            unset($params['createdAt']);

            $request = &$params['request'];
            if (mb_strlen($request) > 255) {
                $request = mb_substr($request, 0, 252) . '...';
            }

            $source = &$params['source'];
            if (($len = mb_strlen($source)) > 100) {
                $source = '...' . mb_substr($source, $len - 97);
            }

            $result = (bool)(new DbModel($this->conf['db_conf_key']))
                ->query($sql, $params)
                ->effect();

        } catch (DbException $e) {
            $this->logIt['message'] .= PHP_EOL . PHP_EOL
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
     * @return bool
     */
    private function writeToFile()
    {
        $writeFunc = function () {
            $logIt = $this->logIt;
            try {
                if (!$logPath = $this->conf['log_path']) {
                    throw new LoggerException('Не задан каталог ("log_path") для лог-файлов.');
                }

                $fn = $logPath . $logIt['createdAt']->format('Ymd') . '_kira_log.csv';

                if ($file = fopen($fn, 'a')) {
                    $result = (bool)fputcsv(
                        $file,
                        [
                            $logIt['type'],
                            $logIt['createdAt']->format(self::FILE_TIME_FORMAT),
                            $logIt['message'],
                            $logIt['source'],
                            $logIt['userIP'],
                            $logIt['request'],
                        ],
                        ';'
                    );
                    fclose($file);
                } else {
                    throw new LoggerException('Не могу писать в файл ' . $fn, LoggerException::RUNTIME_ERROR);
                }
            } catch (LoggerException $e) {
                $this->logIt['message'] .= PHP_EOL . PHP_EOL
                    . 'Дополнительно. Не удалось записать это сообщение в файл лога, причина: ' . $e->getMessage();
                $result = false;
            }
            return $result;
        };

        $result = error_reporting() == 0
            ? System::errorWrapper(\ErrorException::class, $writeFunc)
            : $writeFunc();

        return $result;
    }

    /**
     * Письмо админу с текущим сообщение лога
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
        $date = $logIt['createdAt']->format(self::FILE_TIME_FORMAT);

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
