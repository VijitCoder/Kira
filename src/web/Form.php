<?php
namespace kira\web;

use kira\utils\Arrays;
use kira\net\Request;
use kira\exceptions\FormException;

/**
 * Супер-класс моделей форм (валидации форм).
 *
 * Класс хранит сырые данные с формы и проверенные данные, контроллирует процесс валидации, хранит ошибки.
 *
 * См. документацию, "Модель формы"
 */
class Form
{
    /**
     * Контракт. Описание полей и правил валидации
     * @var array
     */
    protected $contract;

    /**
     * Доступные поля формы. Кеш для магического геттера
     * @var array|null
     */
    protected $availableFields = null;

    /**
     * Объект класса с валидаторами формы. Создается один раз при первом вызове метода валидации.
     * @var FormValidator
     */
    protected $formValidator;

    /**
     * Защита от CSRF атак
     *
     * Если задано имя поля, то форма должна передать CSRF-токен в соответствующем параметре. Чтобы получить токен,
     * используйте методы Request::getCsrfToken() или Request::createCsrfToken().
     *
     * Не нужно объявлять это поле в контракте полей. Тем не менее проверка токена выполняется при валидации формы. Если
     * токен не пройдет проверку, будет проброшено исключение.
     *
     * @var string
     */
    protected $csrfField = '';

    /**
     * Данные с формы. По умолчанию массив заполнен ключами, но без данных.
     * @var array
     */
    protected $rawData;

    /**
     * Данные с формы после очередного валидатора. По умолчанию массив заполнен ключами, но без данных.
     * @var array
     */
    protected $values;

    /**
     * Ошибки валидации
     *
     * По умолчанию массив заполнен ключами, но без данных. По любому полю ошибки хранятся в массиве, даже если
     * там всего одно сообщение. Это необходимо для единообразия: клиентский код всегда может расчитывать на массив.
     * @var array
     */
    protected $errors;

    /**
     * Перегружаем дефолтный контракт создаваемой модели, если он передан в параметре. Инициализируем массивы для данных
     * формы и ошибок валидации. Только ключи, без реальных данных.
     * @param array $contract контракт, с которым предстоит работать
     */
    public function __construct($contract = null)
    {
        if ($contract) {
            $this->contract = $contract;
        }

        $this->rawData =
        $this->errors =
        $this->values = $this->initialArray($this->contract);
    }

    /**
     * Получаем нулевой массив по ключам контракта
     *
     * Требуется обход всего дерева массивов, чтобы извлечь все имена полей с учетом из уровня вложенности.
     * В результате будет копия с массива контракта, содержащая только имена полей, но без каких-либо данных.
     *
     * @param array $arr
     * @return mixed
     */
    private function initialArray($arr)
    {
        $result = [];
        foreach ($arr as $k => $v) {
            if ($k == 'validators') {
                continue;
            }

            $result[$k] = is_array($v) ? $this->initialArray($v) : null;
        }
        return $result ?: null;
    }

    /**
     * Загрузка в класс сырых данных, обычно непосредственно от html-формы
     * @param array $data исходные данные
     * @return $this
     */
    public function load($data)
    {
        $this->rawData = Arrays::merge_recursive($this->rawData, $data);

        return $this;
    }

    /**
     * Валидация. Рекурсивный обход контракта
     * @return bool
     * @throws FormException из checkСsrfToken()
     */
    public function validate()
    {
        if (!$this->checkСsrfToken()) {
            return false;
        }

        if (!$this->formValidator) {
            $this->formValidator = new FormValidator;
        }

        if (!$this->rawData) {
            return false;
        }

        foreach ($this->contract as $key => &$contractPart) {
            $data = isset($this->rawData[$key]) ? $this->rawData[$key] : null;
            $this->formValidator->internalValidate($contractPart, $data, $this->values[$key], $this->errors[$key]);
        }

        return $this->formValidator->isValid();
    }

    /**
     * Проверка CSRF-токена
     *
     * Если поле с токеном не задано, не выполнять проверку. Если не пройдет проверку, пробрасываем исключение.
     *
     * @return true
     * @throws FormException
     * @throws FormException с кодом 400, если токен неверный
     */
    private function checkСsrfToken()
    {
        if (!$this->csrfField) {
            return true;
        }

        $method = Request::method();
        if (!in_array($method, ['GET', 'POST'])) {
            throw new FormException('Проверка CSRF токена возможна только при передаче формы методами GET или POST'
                . PHP_EOL . 'Текущий метод определен, как ' . $method);
        }
        $method = strtolower($method);

        $token = Request::$method($this->csrfField);

        if (!Request::validateCsrfToken($token)) {
            throw new FormException('Неверный CSRF токен', 400);
        }

        return true;
    }

    /**
     * Результат проведенной валидации. Доступен только после проведения валидации.
     * @return bool | null
     */
    public function isValid()
    {
        return $this->formValidator ? $this->formValidator->isValid() : null;
    }

    /**
     * Свое значение в массив валидированных данных.
     *
     * Если значение нужно разместить в многоуровневом массиве, пишем всю вложенность ключей и новое значение на нужном
     * уровне. Старое значение будет переписано, если оно есть.
     *
     * Наличие поля не проверяется, что дает больше возможностей для управления массивом итоговых данных.
     *
     * TODO это плохо или хорошо? С одной стороны, можно добавить что-то после валидации, например, спец.данные,
     * нужные программисту. С другой же стороны, ошибка кодера в имени поля может оказаться незамеченной.
     *
     * @param array $value ключ => начение
     */
    public function setValue($value)
    {
        $this->values = Arrays::merge_recursive($this->values, $value);
    }

    /**
     * Есть в модели информация об ошибках? Необязательно ошибки валидации. Вообще какие-нибудь есть?
     *
     * По умолчанию массив ошибок заполнен ключами, но без данных. Заполнение происходит в конструкторе. А вот если есть
     * хотя бы одно ненулевое значение в массиве, значит есть реальные ошибки.
     *
     * @return bool
     */
    public function hasErrors()
    {
        return $this->internalHasErrors($this->errors);
    }

    private function internalHasErrors($errors)
    {
        foreach ($errors as $k => $v) {
            if (is_string($k) && is_array($v)) {
                if ($this->internalHasErrors($v)) {
                    return true;
                }
            } else if ($v) {
                return true;
            }
        }

        return false;
    }

    /**
     * Добавить свое сообщение в массив ошибок
     *
     * Важно помнить, что по умолчанию массив заполнен ключами, но без данных. Это обеспечено конструктором модели формы.
     *
     * Если сообщение нужно разместить в многоуровневом массиве, пишем всю вложенность ключей и сообщение на нужном
     * уровне.
     *
     * @param array $message ключ => сообщение
     */
    public function addError($message)
    {
        $key = key($message);
        $this->internalAddError($this->errors[$key], $message[$key]);
    }

    /**
     * Добавление сообщения об ошибке в нужном месте многоуровнего массива. Рекурсия.
     * @param mixed        $errors куда писать сообщение
     * @param string|array $msg    текст сообщения ИЛИ массив ключей и в итоге текст сообщения
     */
    private function internalAddError(&$errors, &$msg)
    {
        if (is_array($msg)) {
            $key = key($msg);
            $this->internalAddError($errors[$key], $msg[$key]);
        } else {
            $errors[] = $msg;
        }
    }

    /**
     * Вернуть сырые данные. Весь массив или конкретный элемент.
     *
     * Обычно его используют шаблоны для заполнения формы при ошибках валидации.
     *
     * @param mixed $key ключ в массиве данных. Возможно составной ключ типа "['lvl1' => ['lvl2' => 'param1']]".
     * @return array | string
     */
    public function getRawData($key = null)
    {
        return $this->getData($this->rawData, $key);
    }

    /**
     * Вернуть результаты валидации. Весь массив или конкретный элемент.
     *
     * В каждом элементе либо строка либо false (не прошло валидацию). Инфу о результе валидации в целом можно узнать
     * через {@see Form::isValid()}
     *
     * @param mixed $key ключ в массиве данных. Возможно составной ключ типа "['lvl1' => ['lvl2' => 'param1']]".
     * @return array | string
     */
    public function getValues($key = null)
    {
        return $this->getData($this->values, $key);
    }

    /**
     * Вернуть сообщения об ошибках. Весь массив или конкретный элемент.
     *
     * Если ошибки нет, будет пустой элемент, иначе возвращаем массив, даже если сообщение всего одно.
     *
     * Важно помнить, что по умолчанию массив заполнен ключами, но без данных. Это обеспечено конструктором модели формы.
     *
     * {@see Form::getErrorsAsString()}
     *
     * @param mixed $key ключ в массиве данных. Возможно составной ключ типа "['lvl1' => ['lvl2' => 'param1']]".
     * @return array [поле => массив ошибок]
     */
    public function getErrors($key = null)
    {
        return $this->getData($this->errors, $key);
    }

    /**
     * Работающий метод. Обращается к private массивам класса и возвращает их данные по запросу.
     *
     * Ключ может быть составным, типа "['lvl1' => ['lvl2' => 'param1']]".
     * см. комментарий к kira\utils\Arrays::getValue()
     *
     * @param array  $data массив данных в текущем классе
     * @param string $key   ключ в массиве данных. Возможно составной ключ.
     * @return array | string | null
     */
    private function getData($data, $key)
    {
        if ($key === null) {
            return $data;
        }

        return Arrays::getValue($data, $key);
    }

    /**
     * Получение всех ошибок по каждому полю, склееных в строку.
     *
     * Если контракт - сложный многомерный массив, то ошибки склеиваются из всех подмассивов для каждого старшего ключа.
     * На выходе всегда получается простой ассоциативный массив [поле => ошибки]. В случае сложного контракта полями
     * будут его старшие ключи.
     *
     * @param null   $key  ключ в массиве данных. Возможно составной ключ типа "['lvl1' => ['lvl2' => 'param1']]".
     * @param string $glue клей между соседними элементами одного массива
     * @param string $eol  клей между соседними подмассивами
     * @return array
     */
    public function getErrorsAsString($key = null, $glue = ' ', $eol = '')
    {
        $errors = $this->getErrors($key);

        foreach ($errors as &$v) {
            if (is_array($v)) {
                $v = Arrays::implode_recursive($v, $glue, $eol);
            }
        }

        return $errors;
    }

    /**
     * Геттер поля формы. Значение возращается из массива валидированных данных
     * @param string $name имя поля формы
     * @return mixed
     * @throws FormException
     */
    public function __get($name)
    {
        if (in_array($name, $this->getAvailableFields())) {
            return $this->getValues($name);
        }
        throw new FormException('Не найдено поле формы - ' . $name);
    }

    /**
     * Сеттер поля формы. Значение устанавливается в массиве валидированных данных
     * @param string $name имя поля формы
     * @param mixed $value
     * @throws FormException
     */
    public function __set($name, $value)
    {
        if (in_array($name, $this->getAvailableFields())) {
            return $this->setValue([$name => $value]);
        }
        throw new FormException('Не найдено поле формы - ' . $name);
    }

    /**
     * Получаем список доступных полей формы
     * @return array
     */
    private function getAvailableFields(): array
    {
        if (is_null($this->availableFields)) {
            $this->availableFields = array_keys($this->contract);
        }
        return $this->availableFields;
    }
}
