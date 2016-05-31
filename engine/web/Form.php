<?php
namespace engine\web;

use engine\utils\Arrays;

/**
 * Супер-класс моделей форм (валидации форм).
 * Класс хранит сырые данные с формы и проверенные данные, контроллирует процесс валидации, хранит ошибки.
 */
class Form
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
     * Ошибки валидации. По умолчанию массив заполнен ключами, но без данных. По любому полю ошибки хранятся в массиве,
     * даже если там всего одно сообщение. Это необходимо для единообразия: клиентский код всегда может расчитывать на
     * массив.
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

    /**
     * Свое значение в массив валидированных данных. Если значение нужно разместить в многоуровневом массиве, пишем всю
     * вложенность ключей и новое значение на нужном уровне. Старое значение будет переписано, если оно есть.
     *
     * Наличие поля не проверяется, что дает больше возможностей для управления массивом итоговых данных.
     *
     * @TODO это плохо или хорошо? С одной стороны, можно добавить что-то после валидации, например, спец.данные,
     * нужные программисту. С другой же стороны, ошибка кодера в имени поля может оказаться незамеченной.
     *
     * @param array $value ключ => начение
     * @return void
     */
    public function setValue($value)
    {
        $this->_values = Arrays::merge_recursive($this->_values, $value);
    }

    /**
     * Есть в модели информация об ошибках? Необязательно ошибки валидации. Вообще какие-нибудь есть?
     *
     * Прим: проверка в методе необходима! По умолчанию массив ошибок заполнен ключами, но без данных. Заполнение
     * происходит в конструкторе. Так сделано, чтобы была возможность использовать пустую инициализированную модель
     * формы.
     *
     * @return bool
     */
    public function hasErrors()
    {
        return $this->_internalHasErrors($this->_errors);
    }

    private function _internalHasErrors($errors)
    {
        foreach ($errors as $k => $v) {
            if (is_string($k) && is_array($v)) {
                return $this->_internalHasErrors($v);
            } else if ($v) {
                return true;
            }
        }

        return false;
    }

    /**
     * Добавить сообщение в массив ошибок. Обычно сервис хочет что-то дописать, чтоб шаблон показал. Например, валидация
     * логина прошла, но он, оказывается, занят уже. Это может сказать только сервис.
     *
     * Важно помнить, что по умолчанию массив заполнен ключами, но без данных. Это обеспечено конструктором модели формы.
     *
     * Если сообщение нужно разместить в многоуровневом массиве, пишем всю вложенность ключей и сообщение на нужном
     * уровне.
     *
     * @param array $message ключ => сообщение
     * @return void
     */
    public function addError($message)
    {
        $key = key($message);
        $this->_internalAddError($this->_errors[$key], $message[$key]);
    }

    /**
     * Добавление сообщения об ошибке в нужном месте многоуровнего массива.
     * Рекурсия.
     *
     * @param mixed $errors куда писать сообщение
     * @param string|array $msg текст сообщения ИЛИ массив ключей и в итоге текст сообщения.
     * @return void
     */
    private function _internalAddError(&$errors, &$msg)
    {
        if (is_array($msg)) {
            $key = key($msg);
            $this->_internalAddError($errors[$key], $msg[$key]);
        } else {
            $errors[] = $msg;
        }
    }

    /**
     * Вернуть сырые данные. Весь массив или конкретный элемент. Обычно его используют шаблоны для заполнения формы
     * при ошибках валидации.
     *
     * Если массив многомерный, тогда по ключу можно получить данные только из верхнего уровня массива.
     *
     * @param string $key ключ в массиве данных
     * @return array | string
     */
    public function getRawData($key = null)
    {
        return $this->_getData($this->_rawData, $key);
    }

    /**
     * Вернуть результаты валидации. Весь массив или конкретный элемент. В каждом элементе либо строка
     * либо false (не прошло валидацию). Инфу о результе валидации в целом можно узнать по по флагу self::$isValid
     *
     * Если массив многомерный, тогда по ключу можно получить данные только из верхнего уровня массива.
     *
     * @param string $key ключ в массиве данных
     * @return array | string
     */
    public function getValues($key = null)
    {
        return $this->_getData($this->_values, $key);
    }

    /**
     * Вернуть сообщения об ошибках. Весь массив или конкретный элемент. Если ошибки нет, будет пустой элемент.
     * Зачем возвращать ошибки в массиве, а не в строке? Чтобы была возможность оформить каждую из них в html-теги.
     *
     * Важно помнить, что по умолчанию массив заполнен ключами, но без данных. Это обеспечено конструктором модели формы.
     *
     * Если массив многомерный, тогда по ключу можно получить данные только из верхнего уровня массива.
     *
     * @see Form::getErrorsAsString()
     *
     * @param string $key ключ в массиве данных
     * @return array [поле => массив ошибок]
     */
    public function getErrors($key = null)
    {
        return $this->_getData($this->_errors, $key);
    }

    /**
     * Работающий метод. Обращается к private массивам класса и возвращает их данные по запросу.
     *
     * @param array  &$data массив данных в текущем классе
     * @param string $key   ключ в массиве данных. Если массив многомерный, тогда ключ из верхнего уровня массива.
     * @return array | string | null
     */
    private function _getData(&$data, $key)
    {
        if ($key === null) {
            return $data;
        }

        return isset($data[$key]) ? $data[$key] : null;
    }
}
