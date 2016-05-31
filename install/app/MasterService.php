<?php
/**
 * Создаем новое приложение по заданной конфигурации.
 */

namespace install\app;

use engine\net\Request;
use engine\utils\Arrays;

class MasterService
{
    /**
     * Ошибки валидации, разобранные по блокам в соответствии блокам формы
     * @var array
     */
    private $_errorBlocks = [
        'required' => null,
        'db'       => null,
        'log'      => null,
        'lang'     => null,
    ];

    /**
     * @var object MasterForm
     */
    private $_form;

    public function __construct()
    {
        $this->_form = new MasterForm();
    }

    /**
     * Пустые массивы для выдачи формы без данных.
     * @return array
     */
    public function getInitialData()
    {
        return ['d' => $this->_form->getRawData(), 'errors' => $this->_errorBlocks];
    }

    /**
     * Создаем приложение по заданной форме.
     *
     * Сначала - валидация формы. В случае ошибок объединяем данные, пересобираем массив ошибок и возвращаем контроллеру.
     *
     * @return array|bool
     */
    public function createApp()
    {
        $form = $this->_form;
        $form->load($_POST)->validate();
        $form->setValue(['log' => ['switch' => Request::postAsBool(['log' => 'switch'])]]);

        if (!$form->isValid()) {
            $values = Arrays::array_filter_recursive($form->getValues());
            $raw = $form->getRawData();
            $values = Arrays::merge_recursive($raw, $values);
            return ['d' => $values, 'errors' => $this->recombineErrors($form->getErrors())];
        }

        //dd($form->getValues());
        exit('Stop on ' . __METHOD__); //DBG
        return true;
    }

    /**
     * Пересобираем ошибки в формат, необходимый форме.
     *
     * На входе - многомерный массив с ошибками. На выходе - одномерный массив, всего 4 элемента. Ошибки объеденены
     * по блокам через [br].
     *
     * @param array $arr исходный массив с ошибками
     * @return array
     */
    public function recombineErrors($arr)
    {
        $arr = Arrays::array_filter_recursive($arr);
        $errors = $this->_errorBlocks;

        if (isset($arr['path']) || isset($arr['main_conf'])) {
            $errors['required'][] = 'Тут все пути должны быть заполнены';
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

        return $errors;
    }
}
