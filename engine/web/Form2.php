<?php
namespace engine\web;

use engine\utils\Arrays;

/**
 * Супер-класс моделей форм (валидации форм).
 * Класс хранит сырые данные с формы и проверенные данные, контроллирует процесс валидации, хранит ошибки.
 */
class Form2
{
    /**
     * Контракт. Описание полей и правил валидации
     * @var array;
     */
    protected $contract;

    /**
     * Объект класса с валидаторами формы. Создается один раз при первом вызове метода валидации.
     * @var FormValidator
     */
    protected $formValidator;

    /**
     * Данные с формы. По умолчанию массив заполнен ключами, но без данных.
     * @var array
     */
    private $_rawData;

    /**
     * Данные после валидации. Имя переменной специально подобрано, кроме прочего переводится как "ценности".
     * По умолчанию массив заполнен ключами, но без данных.
     * @var array
     */
    private $_values;

    /**
     * Ошибки валидации. По умолчанию массив заполнен ключами, но без данных.
     * @var array
     */
    private $_errors;

    /**
     * Конструктор.
     * Тут можно перегрузить дефолтный контракт создаваемой модели, хотя обычно это не требуется.
     * @param array $contract контракт, с которым предстоит работать.
     */
    public function __construct($contract = null)
    {
        if ($contract) {
            $this->contract = $contract;
        }

        $this->_rawData =
        $this->_errors =
        $this->_values = $this->_initialArray($this->contract);
    }

    /**
     * Получаем нулевой массив по ключам контракта.
     *
     * Требуется обход всего дерева массивов, чтобы извлечь все имена полей с учетом из уровня вложенности.
     * В результате будет копия с массива контракта, содержащая только имена полей, но без каких-либо данных.
     *
     * @param array $arr
     * @return mixed
     */
    private function _initialArray($arr)
    {
        $result = [];
        foreach ($arr as $k => $v) {
            if (in_array($k, ['expectArray', 'validators'])) {
                continue;
            }

            $result[$k] = is_array($v) ? $this->_initialArray($v) : null;
        }
        return $result ?: null;
    }

    /**
     * Загрузка в класс сырых данных, обычно непосредственно от html-формы.
     *
     * Суть: в конструкторе подготовлен ассоциативный массив, у него объявлены только ключи согласно контракта
     * {@see Form::$contract}. Данных в массиве нет. Здесь объединяем пустой массив с данными. Все левое в нем тоже
     * сохранится, но валидатор проигнорирует поля, не заявленные в контракте. Т.е. в сырых данных будет всё, а в
     * проверенных (self::$_values) - только то, что заявлено в контракте.
     *
     * @param array $data исходные данные
     * @return object указатель на себя для поддержания вызова по цепочке
     */
    public function load(&$data)
    {
        $this->_rawData = Arrays::merge_recursive($this->_rawData, $data);
        return $this;
    }

    /**
     * Валидация. Рекурсивный обход контракта.
     * Прим.: любой из валидаторов внутри рекурсии может установить флаг $_isValid = FALSE в случае ошибок.
     * @return bool
     */
    public function validate()
    {
        if (!$this->formValidator) {
            $this->formValidator = new FormValidator;
        }

        if (!$this->_rawData) {
            return false;
        }

        foreach ($this->contract as $key => &$contractPart) {
            $data = isset($this->_rawData[$key]) ? $this->_rawData[$key] : null;
            $this->formValidator->internalValidate($contractPart, $data, $this->_values[$key], $this->_errors[$key]);
        }
        dd($this->_rawData, $this->_values, $this->_errors); //DBG

        return $this->formValidator->isValid();
    }

    /**
     * Результат проведенной валидации.
     * Доступен только после проведения валидации.
     * @return bool | null
     */
    public function isValid()
    {
        return $this->formValidator ? $this->formValidator->isValid() : null;
    }

    //TODO
    public function getErrors($key = null)
    {
        return;
    }
}
