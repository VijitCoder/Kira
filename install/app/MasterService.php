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

class MasterService
{
    /**
     * @var object MasterForm
     */
    private $form;

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

        if (!$this->rollbackHandler = fopen(APP_PATH . SELF::RBACK_FILE_NAME, 'w')) {
            $this->addToBrief(self::BRIEF_ERROR, 'Не могу создать файл отката. Установка прервана.');
            return $this->endProcess(false);
        }

        if (!$this->createPaths($values)) {
            return $this->endProcess(false);
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

        if (!$isOk) {
            $this->rollback();
        } else {
            $this->addToBrief(self::BRIEF_INFO, 'Создание приложения успешно завершено.');
        }

        Session::newFlash('brief', serialize($this->brief));

        return $isOk;
    }

    /**
     * Откат создания приложения.
     * Удалить каталоги и файлы. Удалить таблицу логера.
     * Прим: запрос может прийти не только из этого сервиса, но из контроллера тоже.
     * @return void
     */
    public function rollback()
    {
        $this->addToBrief(self::BRIEF_INFO, 'Откатываю установку приложения.');

        if (!$fh = fopen(APP_PATH . SELF::RBACK_FILE_NAME, 'r')) {
            $this->addToBrief(self::BRIEF_ERROR, 'Не могу открыть файл отката.');
            return;
        }

        while (($row = fgetcsv($fh)) !== false) {
            $object = $row[1];
            switch ($row[0]) {
                case self::RBACK_PATH:
                    $result = FS::removeDir($object, 2);
                    $tgtInsert = 'каталога';
                    break;
                case self::RBACK_FILE:
                    $result = FS::deleteFile($object);
                    $tgtInsert = 'файла';
                    break;
                case self::RBACK_TABLE:
                    // TODO
                    $tgtInsert = 'таблицы';
                    break;
                default:;
            }

            if ($result !== true) {
                $this->addToBrief(self::BRIEF_WARN, "Ошибка удаления {$tgtInsert}. $result");
            }
        }

        fclose($fh);

        $this->addToBrief(self::BRIEF_INFO, 'Откат закончен.');
    }

    /**
     * Создание каталогов. Определение прав доступа.
     *
     * @param array $v проверенный массив данных
     * @return void
     */
    private function createPaths(&$v)
    {
        $this->addToBrief(self::BRIEF_INFO, 'Создаем каталоги приложения');
        $ok = true;

        /**
         * Создаем каталог. Пишем либо новую цель в файл отката либо полученную ошибку в сводку.
         * @param $path
         */
        $functionCreate = function ($path) use ($ok) {
            if (true === ($result = FS::makeDir($path))) {
                fputcsv($this->rollbackHandler, [self::RBACK_PATH, $path]);
            } else {
                $ok = false;
                $this->addToBrief(self::BRIEF_ERROR, "Ошибка создания каталога '{$path}': {$result}");
            }
        };

        foreach ($v['path'] as &$path) {
            $path = ROOT_PATH . $path;
            $functionCreate($path);
        }

        $v['main_conf'] = ROOT_PATH . $v['main_conf'];
        $functionCreate(dirname($v['main_conf']));

        if ($v['log']['switch']) {
            $path = &$v['log']['path'];
            if (!$path) {
                $path = $v['path']['temp'];
                $this->addToBrief(self::BRIEF_INFO,
                    'Каталог лога не задан. Логи будут складываться в temp-каталог приложения.');
            } else {
                $path = ROOT_PATH . $path;
                $functionCreate($path);
            }
        }

        if ($v['lang']['switch']) {
            $path = &$v['lang']['js_path'];
            $path = ROOT_PATH . $path . 'i18n/';
            $functionCreate($path);
        }

        return $ok;
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
