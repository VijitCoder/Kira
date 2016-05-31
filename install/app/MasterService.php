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

class MasterService
{
    /**
     * @var object MasterForm
     */
    private $_form;

    /**
     * Ошибки, разобранные по блокам в соответствии блокам формы. Массив хранит ошибки валидации или ошибки
     * доп.проверок, выполняющихся после нее. В любом случае - в массиве ошибки, не позволяющие перейти к созданию
     * приложения.
     * @var array
     */
    private $_errorBlocks = [
        'required' => null,
        'db'       => null,
        'log'      => null,
        'lang'     => null,
    ];

    /**
     * Флаг того, что процесс создания приложения все-таки неудался. Флаг не относится к валидации формы и последующим
     * проверкам. Только к реальному форс-мажору.
     * @var bool
     */
    private $_processFailed = false;

    /**
     * Сводка. Двумерный массив сообщений процесса: [type => info|warn|error, message => string]
     * @var array
     */
    private $_brief = [];

    const
        BRIEF_INFO = 1,
        BRIEF_WARN = 2,
        BRIEF_ERROR = 3;

    /**
     * Дескриптор csv-файла для отката установки.
     * @var resource
     */
    private $_rollbackHandler;

    /**
     * MasterService constructor.
     */
    public function __construct()
    {
        $this->_form = new MasterForm();
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
    public function prepareViewData($v = null, $errors = null)
    {
        return [
            'd'      => $v ?: $this->_form->getValues(),
            'errors' => $errors ?: $this->_errorBlocks,
        ];
    }

    /**
     * Создаем приложение по заданной форме.
     *
     * Сначала - валидация формы. В случае ошибок объединяем исходные и валидированные данные, пересобираем массив
     * ошибок в блоки и возвращаем ответ контроллеру.
     *
     * @return array|bool
     */
    public function createApp()
    {
        $form = $this->_form;
        $form->load($_POST)->validate();

        if (!$form->isValid()) {
            $this->_recombineValidationErrors($form->getErrors());

            $v = Arrays::array_filter_recursive($form->getValues());
            $raw = $form->getRawData();
            $v = Arrays::merge_recursive($raw, $v);

            return $this->prepareViewData($v);
        }

//dd($form->getValues());

        if (!$this->_logicChecks()) {
            return $this->prepareViewData();
        }

        exit('Stop on ' . __METHOD__); //DBG

        return true;
    }

    /**
     * Пересобираем ошибки валидации в формат, необходимый форме.
     *
     * На входе - многомерный массив с ошибками. На выходе - одномерный массив, всего 4 элемента. Ошибки объеденены
     * по блокам через [br].
     *
     * @param array $arr исходный массив с ошибками
     * @return void
     */
    private function _recombineValidationErrors($arr)
    {
        $arr = Arrays::array_filter_recursive($arr);
        $errors = &$this->_errorBlocks;

        if (isset($arr['path']) || isset($arr['main_conf'])) {
            $errors['required'][] = 'Все пути должны быть заполнены';
        }

        foreach (['ns_prefix', 'email',] as $key) {
            if (isset($arr[$key])) {
                $errors['required'][] = $arr[$key];
            }
        }

        foreach (['db', 'log', 'lang',] as $key) {
            if (isset($arr[$key])) {
                $errors[$key] = $arr[$key];
            }
        }

        foreach ($errors as &$v) {
            if ($v) {
                $v = Arrays::implode_recursive($v, '<br>');
            }
        }
    }

    /**
     * Доп.проверки:
     * <ul>
     * <li>проверить префикс приложения на совпадение с 'engine'.</li>
     * <li>проверить, что все пути не выходят за корень сайта. Заменить слеши на прямые, в конце поставить слеши.</li>
     * <li>проверить, что нет каталога будущего приложения.</li>
     * <li>проверить, что нет каталога статики js для словарей, если он нужен.</li>
     * <li>проверить подключение к БД. Тут много тонкостей. Исходим из того, что если юзер описал конфиг, значит СУБД -
     * MySQL и подключение должно быть.</li>
     * <li>если включено логирование, проверить, что выбран один из вариантов, куда логировать. Если не выбран, задать
     * значение по умолчанию. Аналогично с путем к логам/именем таблицы в БД.</li>
     * <li>если задана таблица логов, проверить, что ее нет в БД</li>
     * <li>языки. Если флаг есть, а кодов нет и/или пути нет - ошибка. Если коды есть и/или путь есть, но флаг сброшен -
     * предупреждение в сводку.</li>
     * <li>коды языков, каждое имя в списке проверить.</li>
     * </ul>
     *
     * @return bool
     */
    private function _logicChecks()
    {
        $v = $this->_form->getValues();
        $v['log_on'] = isset($v['log']['switch']);
        $v['lang_on'] = isset($v['lang']['switch']);

        if ($v['ns_prefix'] === 'engine') {
            $errors['required'][] = 'Недопустимый префикс приложения. "<i>engine</i>" - это префикс движка, вообще-то.';
        }

        $this->_preparePaths($v, $errors);

        /*
         * TODO проверить существование каталога приложения. Не должно быть.
         * Проверить существование каталога lang.js_path. Не должно быть. Учесть lang.switch = on
         * Проверить каталог log.path. Учесть log.switch = on. Если каталог есть, должен быть доступ на запись в него.
         */

        if ($errors) {
            foreach ($errors as $k => $v) {
                $this->_errorBlocks[$k] = implode('<br>', $v);
            }
            return false;
        }

        return true;
    }

    /**
     * Подготовка каталогов.
     *
     * Дезинфекция каталога: заменяем слеши на прямые, в конце ставим слеш. Проверяем каталог на попытки переходов типа
     * "../path/" или "some/../../path/". Это недопустимо.
     *
     * Приписываем корневой путь. Значения меняем по ссылке, ошибки пишем по ссылке. Функция существует, чтобы
     * разгрузить клиентский метод.
     *
     * @param array $v      исходный массив данных
     * @param array $errors массив, принимающий ошибки
     * @return void
     */
    private function _preparePaths(&$v, &$errors)
    {
        /**
         * Дезинфекция каталога
         * @param string $path
         * @return string|null тест ошибки или NULL. Сам каталог изменится по ссылке.
         */
        $sanitizePath = function (&$path) {
            if (!$path) {
                return;
            }
            $path = trim(str_replace('\\', '/', $path), '/') . '/';
            if (preg_match('~^[.]{2}/|/[.]{2}/~', $path)) {
                return 'каталог должен быть в пределах сайта';
            }
        };


        foreach ($v['path'] as $key => &$path) {
            if ($err = $sanitizePath($path)) {
                $errors['required'][] = "$key: $err";
            } else {
                $path = ROOT_PATH . $path;
            }
        }

        if ($err = $sanitizePath($v['main_conf'])) {
            $errors['required'][] = "main_conf: $err";
        } else {
            $v['main_conf'] = ROOT_PATH . $v['main_conf'];
        }

        $path = &$v['log']['path'];
        if ($err = $sanitizePath($path)) {
            $errors['log'][] = $err;
        } else {
            if (!$path && $v['log_on']) {
                $this->_addToBrief(self::BRIEF_WARN,
                    'Каталог лога не задан. Логи будут складываться в temp-каталог приложения.');
            }
            $path = ROOT_PATH . ($path ?: $v['path']['temp']);
        }

        $path = &$v['lang']['js_path'];
        if ($err = $sanitizePath($path)) {
            $errors['lang'][] = $err;
        } else {
            $path = ROOT_PATH . $path . 'i18n/';
        }
    }

    /**
     * Добавить запись в сводку
     * @param int    $type    см. константы self::BRIEF_*
     * @param string $message сообщение
     * @return void
     */
    private function _addToBrief($type, $message)
    {
        $this->_brief[] = compact('type', 'message');
    }
}
