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
     * Максимальный уровень сообщений в сводке. Переменная нужна для финальной старницы, определяет текст на ней.
     * @var int
     */
    private $briefMaxType = 0;

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

        $rollbackFile = APP_PATH . self::RBACK_FILE_NAME;
        if (!$this->rollbackHandler = fopen($rollbackFile, 'w')) {
            $this->addToBrief(self::BRIEF_ERROR, "Не могу создать файл отката <b>{$rollbackFile}</b>.<br>"
                . 'Проверьте доступ в каталог, права на запись этого файла. Установка прервана.');
            return $this->endProcess(false);
        }

        $exampleMethod = $values['lang']['switch'] ? 'createLangExample' : 'createExample';

        if (!$this->organizePaths($values)
            || !$this->organizeLogger($values)
            || !$this->writeConfig($values)
            || !$this->writeMainFiles($values)
            || !$this->$exampleMethod($values)
        ) {
            return $this->endProcess(false);
        }

        return $this->endProcess(true);
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
     * Окончание/прерывание процесса создания приложения.
     * В массив сводки добавляем первым элементом итоговое состояние установки.
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

        array_unshift($this->brief, $this->briefMaxType);
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
     * Удаляем файл отката, тем исключая возможность откатить установку.
     * @return string|true
     */
    public static function eliminateRollback()
    {
        return FS::deleteFile(APP_PATH . self::RBACK_FILE_NAME);
    }

    /**
     * Создание каталогов. Определение прав доступа.
     * Все каталоги находятся внутри каталога приложения. За исключением каталога js-словарей.
     * Исходное значение "app_path" далее не используем, оно нужно только для индексного файла.
     * @param array $v проверенный массив данных
     * @return bool
     */
    private function organizePaths(&$v)
    {
        $this->addToBrief(self::BRIEF_INFO, 'Создаем каталоги приложения');
        $ok = true;
        $multiLang = $v['lang']['switch'];

        $v['path'] = [
            'app'         => '',
            'view'        => 'views/',
            'temp'        => 'temp/',
            'conf'        => 'conf/',
            'controllers' => 'controllers/',
        ];

        if ($multiLang) {
            $v['path']['i18n'] = 'i18n/';
        }

        foreach ($v['path'] as $key => &$path) {
            $path = ROOT_PATH . $v['app_path'] . $path;
            if (!$this->createPath($path)) {
                $ok = false;
            }
        }

        if ($multiLang) {
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
     * Запись готового текста скрипта в конечный файл.
     * @param string $file имя файла
     * @param string $text текст для сохранения
     * @return bool
     */
    private function writeToFile($file, $text)
    {
        if ($result = file_put_contents($file, $text)) {
            chmod($file, 0666);
            fputcsv($this->rollbackHandler, [self::RBACK_FILE, $file]);
        } else {
            $this->addToBrief(self::BRIEF_ERROR, 'Ошибка создания файла ' . $file);
        }
        return (bool)$result;
    }

    /**
     * Пишем конфигурацию приложения
     * Заморочки в env.php: если нет БД, нужно выпилить лишнее (конфиг DB, часть конфига логера). Аналогично, если
     * нет логера, нужно полностью убрать его конфигурацию из шаблона.
     * @param array $v проверенный массив данных
     * @return bool
     */
    private function writeConfig(&$v)
    {
        $this->addToBrief(self::BRIEF_INFO, 'Пишем конфигурацию приложения');

        $confPath = $v['path']['conf'];

        $d = ['app_namespace' => $v['app_namespace']];

        # main.php

        $text = Render::fetch($this->piecesPath . "conf/main.php.ptrn", $d);
        if (!$this->writeToFile($confPath . 'main.php', $text)) {
            return false;
        }

        # routes.php

        $text = Render::fetch($this->piecesPath . "conf/routes.php.ptrn", $d);
        if (!$this->writeToFile($confPath . 'routes.php', $text)) {
            return false;
        }

        # env.php

        $d = [];

        if ($v['db']['switch']) {
            $d = $v['db'];
        }

        if ($v['log']['switch']) {
            if ($path = $v['log']['path']) {
                $temp = $v['path']['temp'];
                $tempLen = mb_strlen($temp);
                $part = mb_substr($path, 0, $tempLen);

                if ($part === $temp) {
                    if (mb_strlen($part) == mb_strlen($path)) {
                        $path = 'TEMP_PATH,';
                    } else {
                        $path = substr($path, $tempLen);
                        $path = "TEMP_PATH . '$path',";
                    }
                } else {
                    $path = "'$path',";
                }
            } else {
                $path = "'', // логирование в файлы отключено";
            }
            unset($temp, $tempLen, $part);

            $d['log_path'] = $path;
            $d['log_store'] = '\engine\Log::' . ($v['log']['store'] == 'db' ? 'STORE_IN_DB' : 'STORE_IN_FILES');
            $d['log_table'] = $v['log']['table'] ?: MasterForm::LOG_TABLE;
            $d['log_tz'] = $v['log']['timezone'];
        }

        $d['email'] = $v['email'];

        $text = Render::fetch($this->piecesPath . "conf/env.php.ptrn", $d);

        if ($v['db']['switch']) {
            $text = str_replace(['__DB', 'DB__'], '', $text);
        } else {
            $text = preg_replace('~\n__DB.*DB__~s', '', $text);
            $text = preg_replace('~__L1.*L1__~s', '', $text);
        }

        if ($v['log']['switch']) {
            $text = str_replace(['__LOG', 'LOG__'], '', $text);
            $text = str_replace(['__L1', 'L1__'], '', $text);
        } else {
            $text = preg_replace('~\n__LOG.*LOG__~s', '', $text);
        }

        if (!$this->writeToFile($confPath . 'env.php', $text)) {
            return false;
        }

        return true;
    }

    /**
     * Пишем индексный файл приложения и главный .htaccess.
     * Изначально, в корне сайта этих файлов нет. Создаем их. Если что-то уже есть, дописываем преффикс приложения,
     * кодеру выдадим предупреждение.
     * .htaccess просто копируем, в нем нет подстановок. Но для поддержания единого кода делаем вид, что они есть.
     * @param array $v проверенный массив данных
     * @return bool
     */
    private function writeMainFiles(&$v)
    {
        $this->addToBrief(self::BRIEF_INFO, 'Пишем файлы корня сайта');

        $file_prefix = preg_replace('~[^a-z]~i', '', $v['app_namespace']);

        $targets = [
            'index.php.ptrn' => 'index.php',
            '.htaccess'      => '.htaccess',
        ];

        foreach ($targets as $fileFrom => $fileTo) {
            $fn = ROOT_PATH . $fileTo;
            if (file_exists($fn)) {
                $fnNew = $file_prefix . '_' . $fileTo;
                $this->addToBrief(self::BRIEF_WARN, "Файл $fn уже существует. Создаю <i>$fnNew</i>.");
                $fn = ROOT_PATH . $fnNew;
                if (file_exists($fn)) {
                    $this->addToBrief(self::BRIEF_ERROR, "Файл $fn тоже существует! Не знаю, что делать.");
                    return false;
                }
            }

            $d = [
                'app_namespace' => $v['app_namespace'],
                'app_path'      => $v['app_path'],
            ];

            $text = Render::fetch($this->piecesPath . $fileFrom, $d);
            if (!$this->writeToFile($fn, $text)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Создаем пример приложения: контроллер, шаблон, макет.
     * @param array $v проверенный массив данных
     * @return bool
     */
    private function createExample(&$v)
    {
        $this->addToBrief(self::BRIEF_INFO, 'Создаем пример приложения');

        $addTargets = [
            'views/welcome.htm' => $v['path']['view'] . 'welcome.htm',
        ];

        return $this->internalCreateExample($v, $addTargets);
    }

    /**
     * Создаем пример мультиязычного приложения: контроллер, шаблон, макет, словари.
     * В примере создаем английскую версию сайта, поэтому для нее словари создаются особым способом. Для других языков
     * генерация из болванок.
     * @param array $v проверенный массив данных
     * @return bool
     */
    private function createLangExample(&$v)
    {
        $this->addToBrief(self::BRIEF_INFO, 'Создаем пример мультиязычного приложения');

        $addTargets = [
            'views/lang_welcome.htm.ptrn' => $v['path']['view'] . 'welcome.htm',

            'i18n/en.php' => $v['path']['i18n'] . 'en.php',
            'i18n/en.js'  => $v['lang']['js_path'] . 'en.js',
            'i18n/ru.js'  => $v['lang']['js_path'] . 'ru.js',
        ];

        $langs = explode(',', $v['lang']['other']);
        $langs = array_diff($langs, ['en', 'ru']);

        foreach ($langs as $lang) {
            $addTargets['i18n/dummy.php'] = $v['path']['i18n'] . "{$lang}.php";
            $addTargets['i18n/dummy.js'] = $v['lang']['js_path'] . "{$lang}.js";
        }

        $url = mb_substr($v['lang']['js_path'], mb_strlen(ROOT_PATH) - 1); // -1, чтоб с ведущим слешем
        $addData = [
            'js_url' => $url,
        ];

        return $this->internalCreateExample($v, $addTargets, $addData);
    }

    /**
     * Создаем пример приложения: контроллер, шаблон, макет, другие файлы.
     * "Цели" это файлы для копирования. Если файл заканчивается на .ptrn, значит это шаблон и он требует "отрисовку"
     * перед копированием. Создаваемый пример может быть для мультиязычного или простого приложения. Список целей
     * и данные для вставки у них отличается.
     * @param array $v          проверенный массив данных
     * @param array $addTargets дополнительные цели
     * @param array $addData    дополнительные данные для вставки в шаблоны
     * @return bool
     */
    private function internalCreateExample(&$v, &$addTargets, &$addData = [])
    {
        $targets = [
            'controllers/WelcomeController.php.ptrn' => $v['path']['controllers'] . 'WelcomeController.php',

            'Env.php.ptrn'       => $v['path']['app'] . 'Env.php',
            '.htaccess_for_temp' => $v['path']['temp'] . '.htaccess',
            'views/layout.htm'   => $v['path']['view'] . 'layout.htm',
        ];
        $targets = array_merge($targets, $addTargets);

        $data = array_merge(['app_namespace' => $v['app_namespace']], $addData);

        foreach ($targets as $fileFrom => $fileTo) {
            $text = substr($fileFrom, -5) == '.ptrn'
                ? Render::fetch($this->piecesPath . $fileFrom, $data)
                : file_get_contents($this->piecesPath . $fileFrom);
            if (!$this->writeToFile($fileTo, $text)) {
                return false;
            }
        }

        return true;
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

        foreach (['app_path', 'app_namespace', 'email',] as $key) {
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
     * Добавить запись в сводку.
     * Запоминаем максимальный тип сообщения в сводке.
     * @param int    $type    см. константы self::BRIEF_*
     * @param string $message сообщение
     * @return void
     */
    private function addToBrief($type, $message)
    {
        $this->brief[] = compact('type', 'message');
        if ($type > $this->briefMaxType) {
            $this->briefMaxType = $type;
        }
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
