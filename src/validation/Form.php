<?php
namespace kira\validation;

use kira\utils\Arrays;
use kira\net\Request;
use kira\exceptions\FormException;

/**
 * Супер-класс моделей форм (валидации форм).
 *
 * Класс хранит сырые данные с формы и проверенные данные, инициирует процесс валидации, хранит ошибки.
 *
 * См. документацию, "Модель формы"
 */
class Form
{
    /**
     * Как должно называться поле формы, если потребуется передача CSRF-токена
     */
    const CSRF_FIELD = 'csrf-token';

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
     * Защита от CSRF атак. Если токен не пройдет проверку, будет проброшено исключение.
     *
     * Токен ищем среди данных по ключу "csrf-token" или в заголовке запроса "X-Csrf-Token"
     *
     * @var string
     */
    protected $checkCsrf = false;

    /**
     * Данные с формы. По умолчанию массив заполнен ключами, но без данных.
     * @var array
     */
    protected $rawData = [];

    /**
     * Данные с формы после очередного валидатора. По умолчанию массив заполнен ключами, но без данных.
     * @var array
     */
    protected $values = [];

    /**
     * Ошибки валидации
     *
     * По умолчанию массив заполнен ключами, но без данных. По любому полю ошибки хранятся в массиве, даже если
     * там всего одно сообщение. Это необходимо для единообразия: клиентский код всегда может расчитывать на массив.
     * @var array
     */
    protected $errors = [];

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

        $this->formValidator = new FormValidator(new ValidatorFactory);
    }

    /**
     * Получаем нулевой массив по ключам контракта
     *
     * Требуется обход всего дерева массивов, чтобы извлечь все имена полей с учетом из уровня вложенности.
     * В результате будет копия с массива контракта, содержащая только имена полей, но без каких-либо данных.
     *
     * @param array $arr контракт или его часть
     * @return mixed
     */
    private function initialArray(array $arr)
    {
        $result = [];
        foreach ($arr as $k => $v) {
            if (in_array($k, ['validators', 'default'])) {
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
    public function load(array $data)
    {
        $this->rawData = Arrays::merge_recursive($this->rawData, $data);

        return $this;
    }

    /**
     * Валидация. Рекурсивный обход контракта
     * @return bool
     * @throws FormException из checkCsrfToken() и валидаторов
     */
    public function validate(): bool
    {
        if (!$this->rawData) {
            return false;
        }

        if (!$this->checkCsrfToken()) {
            return false;
        }

        foreach ($this->contract as $key => $contractPart) {
            if (isset($this->rawData[$key])) {
                $this->values[$key] = $this->rawData[$key];
            }
            $this->formValidator->validate($contractPart, $this->values[$key], $this->errors[$key]);
        }

        return $this->formValidator->isValid();
    }

    /**
     * Проверка CSRF-токена
     *
     * Токен ищем среди данных по ключу "csrf-token" или в заголовке запроса "X-Csrf-Token". Если не пройдет проверку,
     * пробрасываем исключение.
     *
     * @return bool
     * @throws FormException
     * @throws FormException с кодом 400, если токен неверный
     */
    private function checkCsrfToken(): bool
    {
        if (!$this->checkCsrf) {
            return true;
        }

        $token = $this->getRawData(self::CSRF_FIELD) ?? Request::headers(CsrfProtection::HEADER_NAME);

        if (!$token || !CsrfProtection::validate($token)) {
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
     * Свое значение в массив валидированных данных. Можно установить несколько значений либо одиним вызовом или по
     * цепочке несколько раз вызвать этот метод.
     *
     * Если значение нужно разместить в многоуровневом массиве, пишем всю вложенность ключей и новое значение на нужном
     * уровне. Старое значение будет переписано, если оно есть.
     *
     * Наличие поля не проверяется, что дает больше возможностей для управления массивом итоговых данных.
     *
     * @param array $value ключ => начение
     * @return $this
     */
    public function setValue(array $value)
    {
        $this->values = Arrays::merge_recursive($this->values, $value);
        return $this;
    }

    /**
     * Есть в модели информация об ошибках? Необязательно ошибки валидации. Вообще какие-нибудь есть?
     *
     * По умолчанию массив ошибок заполнен ключами, но без данных. Заполнение происходит в конструкторе. А вот если есть
     * хотя бы одно ненулевое значение в массиве, значит есть реальные ошибки.
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return $this->internalHasErrors($this->errors);
    }

    /**
     * Проверка наличия ошибок. Рекурсивный обход всего дерева ошибок, отражающего контракт формы.
     * @param array $errors
     * @return bool
     */
    private function internalHasErrors(array $errors): bool
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
    public function addError(array $message)
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
     * Если ошибки нет, будет NULL, иначе возвращаем массив, даже если сообщение всего одно.
     *
     * Важно помнить, что по умолчанию массив заполнен ключами, но без данных. Это обеспечено конструктором модели формы.
     *
     * {@see Form::getErrorsAsStringPerField()}
     *
     * @param mixed $key ключ в массиве данных. Возможно составной ключ типа "['lvl1' => ['lvl2' => 'param1']]".
     * @return array [поле => массив ошибок]
     */
    public function getErrors($key = null): ?array
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
     * @param string $key  ключ в массиве данных. Возможно составной ключ.
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
     * Получение всех ошибок по каждому полю или по конкретному полю. Ошибки поля склеваются в строку.
     *
     * На выходе получаем: если поле задано - будет просто строка, все ошибки поля в ней; если поле не задано, будет
     * одномерный массив, где ключи - имена полей, значения - все ошибки в строку по каждому полю отдельно.
     *
     * @param null   $key  ключ в массиве данных. Возможно составной ключ типа "['lvl1' => ['lvl2' => 'param1']]".
     * @param string $glue клей между соседними элементами одного массива
     * @param string $eol  клей между соседними подмассивами
     * @return array|string|null
     */
    public function getErrorsAsStringPerField($key = null, string $glue = ' ', string $eol = '')
    {
        if (!$errors = $this->getErrors($key)) {
            return $errors;
        }

        if ($key) {
            $errors = Arrays::implode_recursive($errors, $glue, $eol);
        } else {
            foreach ($errors as &$v) {
                if (is_array($v)) {
                    $v = Arrays::implode_recursive($v, $glue, $eol);
                }
            }
        }

        return $errors;
    }

    /**
     * Геттер поля формы. Значение возращается из массива валидированных данных.
     * @param string $name имя поля формы
     * @return mixed
     * @throws FormException
     */
    public function __get(string $name)
    {
        if (array_key_exists($name, $this->values)) {
            return $this->values[$name];
        }
        throw new FormException('Не найдено поле формы - ' . $name);
    }

    /**
     * Сеттер поля формы. Значение устанавливается в массиве валидированных данных.
     * @param string $name имя поля формы
     * @param mixed  $value
     */
    public function __set(string $name, $value)
    {
        $this->setValue([$name => $value]);
    }
}
