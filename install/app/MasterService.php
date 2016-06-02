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
     * Флаг того, что процесс создания приложения все-таки неудался. Флаг не относится к валидации формы и последующим
     * проверкам. Только к реальному форс-мажору.
     * @var bool
     */
    private $processFailed = false;

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

        // TODO собственно процесс создания приложения

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
}
