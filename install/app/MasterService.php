<?php
/**
 * Создаем новое приложение по заданной конфигурации.
 * План:
 * - валидация данных формы
 * - доп. проверки
 * - создание каталогов, файлов, таблицы
 * - сводка
 */

namespace install\app;

use engine\utils\Arrays;
use engine\utils\FS;
use engine\net\Session;
use engine\html\Render;

class MasterService
{
    /**
     * Экземпляр класса модели формы
     * @var object MasterForm
     */
    private $form;

    /**
     * Путь к шаблонам, из которых будет создано приложение
     * @var string
     */
    private $piecesPath;

    /**
     * Ошибки, разобранные по блокам в соответствии блокам формы. Массив хранит ошибки валидации или ошибки
     * доп.проверок, выполняющихся после нее. В любом случае - в массиве ошибки, не позволяющие перейти к созданию
     * приложения.
     * @var array
     */
    private $errorBlocks = [
        'required' => null,
        'db'       => null,
        'log'      => null,
        'lang'     => null,
    ];

    /**
     * Сводка. Двумерный массив сообщений процесса: [type => info|warn|error, message => string]
     * @var array
     */
    private $brief = [];

    const
        BRIEF_INFO = 1,
        BRIEF_WARN = 2,
        BRIEF_ERROR = 3;

    /**
     * Дескриптор csv-файла для отката установки.
     * @var resource
     */
    private $rollbackHandler;

    const RBACK_FILE_NAME = 'rollback.csv';

    // типы целей для удаления
    const
        RBACK_PATH = 'path',
        RBACK_FILE = 'file',
        RBACK_TABLE = 'table';

    /**
     * MasterService constructor.
     */
    public function __construct()
    {
        $this->form = new MasterForm();
        $this->piecesPath = ROOT_PATH . 'install/pieces/';
    }

    /**
     * Данные для заполнения шаблона. Единый метод, определяющий формат ответа для шаблона формы.
     * Используются для инициализации формы в GET-запросе и для ее заполнения при неудачном POST. При этом на форму
     * выводятся ошибки валидации или доп.проверок.
     *
     * @param array $v      значения полей
     * @param array $errors ошибки, собранные в блоки
     * @return array
     */
    public function prepareViewData(&$v = null, &$errors = null)
    {
        return [
            'd'      => $v ?: $this->form->getValues(),
            'errors' => $errors ?: $this->errorBlocks,
        ];
    }

    /**
     * Создаем приложение по заданной форме.
     *
     * Сначала - валидация формы и доп.проверки. В случае ошибок объединяем исходные и валидированные данные,
     * пересобираем массив ошибок в блоки и возвращаем ответ контроллеру.
     *
     * @return array|bool
     */
    public function createApp()
    {
        $form = $this->form;

        $form->load($_POST)->validate();

        if (!($form->isValid() && $form->logicChecks())) {
            $this->errorBlocks = $this->recombineValidationErrors($form->getErrors());

            $values = Arrays::array_filter_recursive($form->getValues());
            $raw = $form->getRawData();
            $values = Arrays::merge_recursive($raw, $values);

            return $this->prepareViewData($values, $this->errorBlocks);
        }

        $values = $form->getValues();

        if (!$this->rollbackHandler = fopen(APP_PATH . self::RBACK_FILE_NAME, 'w')) {
            $this->addToBrief(self::BRIEF_ERROR, 'Не могу создать файл отката. Установка прервана.');
            return $this->endProcess(false);
        }

        if (!$this->organizePaths($values)
            || !$this->organizeLogger($values)
            || !$this->writeConfig($values)
        ) {
            //return $this->endProcess(false);   // Временно отключил
        }

        // DBG
        echo 'DEBUG';
        $this->endProcess(1);
        dd($this->brief);
        exit('Stop ' . __METHOD__);

        return $this->endProcess(true);
    }

    /**
     * Окончание/прерывание процесса создания приложения
     * @param bool $isOk
     * @return bool
     */
    private function endProcess($isOk)
    {
        if ($this->rollbackHandler) {
            fclose($this->rollbackHandler);
        }

        if ($isOk) {
            $this->addToBrief(self::BRIEF_INFO, 'Создание приложения успешно завершено.');
        } else {
            $this->rollback();
        }

        Session::newFlash('brief', serialize($this->brief));

        return $isOk;
    }

    /**
     * Откат создания приложения. Выполняем его в обратном порядке. Это непринципиально, но в сводке выглядит логичнее.
     * Удалить каталоги и файлы. Удалить таблицу логера.
     * Прим: запрос может прийти не только из этого сервиса, но из контроллера тоже. В этом случае нет подключения к БД,
     * таблицу нужно будет удалять вручную. Не хочу придумывать, как бы мне организовать такое подключение.
     * @return void
     */
    public function rollback()
    {
        $file = APP_PATH . self::RBACK_FILE_NAME;

        if (!file_exists($file)) {
            $this->addToBrief(self::BRIEF_ERROR, 'Не найден файл отката.');
            return;
        }

        $this->addToBrief(self::BRIEF_INFO, 'Откатываю установку приложения.');

        if (!$fh = fopen($file, 'r')) {
            $this->addToBrief(self::BRIEF_ERROR, 'Не могу открыть файл отката.');
            return;
        }

        $objects = [];
        while (($row = fgetcsv($fh)) !== false) {
            $objects[] = $row;
        }
        $objects = array_reverse($objects, true);

        foreach ($objects as $v) {
            list($type, $target) = $v;
            switch ($type) {
                case self::RBACK_PATH:
                    $result = FS::removeDir($target, 1);
                    $tgtInsert = 'каталога';
                    break;
                case self::RBACK_FILE:
                    $result = FS::deleteFile($target);
                    $tgtInsert = 'файла';
                    break;
                case self::RBACK_TABLE:
                    $result = true;
                    $tgtInsert = 'таблицы';

                    $dbh = SingleModel::getConnection();

                    if (is_null($dbh)) {
                        if ($conf = Session::read('db')) {
                            Session::delete('db');
                            $conf = unserialize($conf);
                            $dbh = SingleModel::dbConnect($conf);
                        } else {
                            $this->addToBrief(self::BRIEF_WARN,
                                "Нет конфига подключения к БД. Удалите вручную таблицу `$target`");
                            break;
                        }
                    }

                    if ($dbh) {
                        if (!SingleModel::dropLogTable($target)) {
                            $result = SingleModel::getLastError();
                        }
                    } else {
                        $this->addToBrief(self::BRIEF_WARN,
                            'Ошибка соединения. ' . SingleModel::getLastError() . " Удалите вручную таблицу `$target`");
                    }
                    break;
                default:
                    $tgtInsert = $type;
                    $result = 'Неизвестный тип объекта удаления.';
            }

            if ($result === true) {
                $this->addToBrief(self::BRIEF_INFO, "Удаление {$tgtInsert}: $target");
            } else {
                $this->addToBrief(self::BRIEF_WARN, "Ошибка удаления {$tgtInsert}: $target. $result");
            }
        }

        fclose($fh);

        if (true !== ($result = FS::deleteFile($file))) {
            $this->addToBrief(self::BRIEF_ERROR, "Ошибка удаления файла отката. $result");
        }

        $this->addToBrief(self::BRIEF_INFO, 'Откат закончен.');
    }

    /**
     * Создание каталогов. Определение прав доступа.
     *
     * @param array $v проверенный массив данных
     * @return bool
     */
    private function organizePaths(&$v)
    {
        $this->addToBrief(self::BRIEF_INFO, 'Создаем каталоги приложения');
        $ok = true;

        foreach ($v['path'] as &$path) {
            $path = ROOT_PATH . $path;
            if (!$this->createPath($path)) {
                $ok = false;
            }
        }

        $v['main_conf'] = ROOT_PATH . $v['main_conf'];
        if (!$this->createPath(dirname($v['main_conf']))) {
            $ok = false;
        }

        if ($v['lang']['switch']) {
            $path = &$v['lang']['js_path'];
            $path = ROOT_PATH . $path . 'i18n/';
            if (!$this->createPath($path)) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Организуем окружение логера. Метод построен с учетом всех возможностей конфигурации логера,
     * см. соответсвующий док.
     *
     * Каталог. Просто создать. Валидатор уже обеспечил нужное значение.
     *
     * Таблица тоже определена и проверна валидаторомю. Подключаемся к базе. Конфиг подключения сохраняем в сессии
     * для будущего отката, если он будет запрошен кодером отдельно, через контроллер. Создаем таблицу логера.
     *
     * Возможно, что будет задан и путь к файлам и таблица логера. Это нормально и нужно все организовать.
     *
     * @param array $values проверенный массив данных
     * @return bool
     */
    private function organizeLogger(&$values)
    {
        $log = &$values['log'];

        if (!$log['switch']) {
            return true;
        }

        $this->addToBrief(self::BRIEF_INFO, 'Создаем окружение логера');

        $path = &$log['path'];
        if ($path) {
            $path = ROOT_PATH . $path;
            if (($path !== $values['path']['temp']) && !$this->createPath($path)) {
                return false;
            }
        }

        if ('db' == $log['store']) {
            if (!SingleModel::dbConnect($values['db'])) {
                $this->addToBrief(self::BRIEF_ERROR, 'Не удалось подключиться в базе: ' . SingleModel::getLastError());
                return false;
            };

            Session::write('db', serialize($values['db']));

            $table = &$log['table'];
            if ($result = SingleModel::createLogTable($table)) {
                $this->addToBrief(
                    self::BRIEF_INFO,
                    "Создана таблица логера `$table` в базе, описанной в конфигурации БД"
                );
                fputcsv($this->rollbackHandler, [self::RBACK_TABLE, $table]);
            } else {
                $this->addToBrief(self::BRIEF_ERROR, 'Не удалось создать таблицу. ' . SingleModel::getLastError());
            }

            return $result;
        }

        return true;
    }

    /**
     * Создаем каталог. Пишем либо новую цель в файл отката либо полученную ошибку в сводку.
     * @param string $path
     * @return bool
     */
    private function createPath($path)
    {
        if (true === ($result = FS::makeDir($path))) {
            fputcsv($this->rollbackHandler, [self::RBACK_PATH, $path]);
        } else {
            $this->addToBrief(self::BRIEF_ERROR, "Ошибка создания каталога '{$path}': {$result}");
        }

        return $result === true;
    }

    /**
     * Пишем конфигурацию приложения
     * @param array $v проверенный массив данных
     * @return bool
     */
    private function writeConfig(&$v)
    {
        $this->addToBrief(self::BRIEF_INFO, 'Пишем конфигурацию приложения');

        $confPath = dirname($v['main_conf']);

        $d = ['ns_prefix' => $v['ns_prefix']];

        # main.php

        $text = Render::fetch($this->piecesPath . "conf/main.php.ptrn", $d);
        if (!$this->writeToFile($v['main_conf'], $text)) {
            return false;
        }

        # routes.php

        $text = Render::fetch($this->piecesPath . "conf/routes.php.ptrn", $d);
        if (!$this->writeToFile($confPath . '/routes.php',  $text)) {
            return false;
        }

        # env.php

        $d = [];

        if ($v['db']['switch']) {
            $d = $v['db'];
        }

        if ($v['log']['switch']) {
            $d['log_store'] = '\engine\Log::' . ($v['log']['store'] == 'db' ? 'STORE_IN_DB' : 'STORE_IN_FILES');
            if ($path = $v['log']['path']) {
                $temp = $v['path']['temp'];
                $cmp = strcmp($path, $temp);
                if ($cmp == 0) {
                    $path = 'TEMP_PATH,';
                } else if ($cmp > 0) {
                    $path = substr($path, mb_strlen($temp));
                    $path = "TEMP_PATH . '$path',";
                } else {
                    $path = "'$path',";
                }
            } else {
                $path = "'', // логирование в файлы отключено";
            }
            unset($cmp, $temp);

            $d['log_path'] = $path;
            $d['log_tz'] = $v['log']['timezone'];
        }

        $text = Render::fetch($this->piecesPath . "conf/env.php.ptrn", $d);

        if ($v['db']['switch']) {
            $text = str_replace(['__DB', 'DB__'], '', $text);
        } else {
            $text = preg_replace('~\n__DB.*DB__~s', '', $text);
            $text = preg_replace("~'db_conf_key'.*\n\s{8}~", '', $text);
        }

        if ($v['log']['switch']) {
            $text = str_replace(['__LOG', 'LOG__'], '', $text);
        } else {
            $text = preg_replace('~\n__LOG.*LOG__~s', '', $text);
        }

        if (!$this->writeToFile($confPath . '/env.php', $text)) {
            return false;
        }

        return true;
    }

    /**
     * Запись готового текста скрипта в конечный файл.
     * @param string $file имя файла
     * @param string $text текст для сохранения
     * @return bool
     */
    private function writeToFile($file, $text)
    {
        if ($result = file_put_contents($file, $text)) {
            fputcsv($this->rollbackHandler, [self::RBACK_FILE, $file]);
        } else {
            $this->addToBrief(self::BRIEF_ERROR, 'Ошибка создания файла ' . $file);
        }
        return (bool)$result;
    }

    /**
     * Пересобираем ошибки валидации в формат, необходимый форме.
     *
     * На входе - многомерный массив с ошибками. На выходе - одномерный массив, всего 4 элемента. Ошибки объеденены
     * по блокам через [br].
     *
     * @param array $errors исходный массив с ошибками
     * @return array
     */
    private function recombineValidationErrors($errors)
    {
        $errors = Arrays::array_filter_recursive($errors);
        $result = $this->errorBlocks;

        foreach (['path', 'main_conf', 'ns_prefix', 'email',] as $key) {
            if (isset($errors[$key])) {
                $result['required'][] = $errors[$key];
            }
        }

        foreach (['db', 'log', 'lang',] as $key) {
            if (isset($errors[$key])) {
                $result[$key] = $errors[$key];
            }
        }

        foreach ($result as &$v) {
            if ($v) {
                $v = Arrays::implode_recursive($v, '<br>');
            }
        }

        return $result;
    }

    /**
     * Добавить запись в сводку
     * @param int    $type    см. константы self::BRIEF_*
     * @param string $message сообщение
     * @return void
     */
    private function addToBrief($type, $message)
    {
        $this->brief[] = compact('type', 'message');
    }

    /**
     * Получить текущую сводку. Геттер.
     * @return array
     */
    public function getBrief()
    {
        return $this->brief;
    }
}
