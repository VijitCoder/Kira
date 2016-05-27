<?php
namespace engine\web;

use engine\App;

/**
 * Служебный класс для модели формы. Содержит только методы валидации. Готовые данные и ошибки передаются в клиентский
 * класс и тут не хранятся.
 */
class FormValidator
{
    /**
     * Флаг результата валидации, успешно или нет.
     * @var bool
     */
    protected $isValid = true;

    /**
     * Предохранитель. Если будет вызван типовой валидатор, неизвестный классу, пробросим исключение. Это должно помочь
     * разработчику в поиске ошибки.
     *
     * @param $method
     * @param $params
     * @return void
     * @throws \LogicException
     */
    public function __call($method, $params)
    {
        if (strncmp($method, 'validator', 8) === 0) {
            throw new \LogicException('Неизвестный типовой валидатор: ' . $method . PHP_EOL
                . 'Проверьте правильность контракта формы.');
        }
    }

    /**
     * Валидация одного узла дерева контракта. Рекурсия.
     *
     * Функция не предполагает прямой вызов из кода приложения. Это часть логики движка.
     *
     * Узел может быть равен нулю только если он конечный и в контракте заявлено принять поле "как есть". Тогда пишем
     * полученные данные без валидации и выходим.
     *
     * Если узел не конечный (является веткой), тогда в нем не может быть валидаторов, есть только дочерний массив
     * узлов. Для каждого такого узла рекурсивно вызываем этот же метод.
     *
     * @param array|null $contractPart описательная часть контракта по конкретному узлу
     * @param mixed      $data         проверяемые данные. Соответствуют узлу в массиве self::$_rawdata
     * @param mixed      $value        куда писать валидированное значение. Соответствует узлу в self::$_values
     * @param mixed      $error        куда писать ошибку. Соответствует узлу в self::$_errors.
     * @return void
     * @throws \LogicException через магический FormValidator::__call()
     */
    public function internalValidate(&$contractPart, &$data, &$value, &$error)
    {
        if (!$contractPart) {
            $value = $data;
            return;
        }

        if (!isset($contractPart['validators'])) {
            foreach ($contractPart as $k => $cp) {
                $d = isset($data[$k]) ? $data[$k] : null;
                $this->internalValidate($cp, $d, $value[$k], $error[$k]);
            }
            return;
        }

        $validators = $contractPart['validators'];

        $required = isset($validators['required']) ? $validators['required'] : false;
        if ($this->canSkip($data, $required, $value, $error)) {
            return;
        }

        unset($validators['required']);

        $expectArray = isset($contractPart['expectArray']) && $contractPart['expectArray'] == true;
        if (!$this->checkDataType($data, $expectArray, $error)) {
            return;
        }

        // каждый последующий валидатор должен работать с данными, обработанными предыдущим.
        $newData = $data;
        foreach ($validators as $type => $description) {
            $method = 'validator' . ucfirst($type);
            if ($expectArray) {
                foreach ($newData as $k => $d) {
                    $this->$method($description, $d, $value[$k], $error[$k]);
                }
            } else {
                $this->$method($description, $newData, $value, $error);
            }
            $newData = $value;
        }
    }

    /**
     * Можно ли пропустить проверки.
     *
     * Если поле пустое, нет смысла его валидировать. При этом нужно проверить, задана ли в контакте его обязательность.
     * Внутри функции пишем значение поля, возможно клиенту важно, какая именно пустота (строка, массив и т.д.).
     * Пишем ошибку валидатора "required", если она есть.
     *
     * @param mixed      $data     проверяемые данные
     * @param array|bool $required настройки валидатора "required"
     * @param mixed      $value    куда писать значение
     * @param mixed      $error    куда писать ошибку
     * @return bool можно пропустить поле?
     */
    public function canSkip(&$data, &$required, &$value, &$error)
    {
        $skip = empty($data);

        if ($skip) {
            $value = $data;

            if ($required) {
                $this->isValid = false;
                $error = isset($required['message'])
                    ? $required['message']
                    : App::t('Поле должно быть заполнено');
            }
        }

        return $skip;
    }

    /**
     * Проверяем данные на соответствие ожиданиям.
     * Есть две ситуации: ждем массив или любой другой тип данных. Если ожидания не оправдались - ошибка валидации.
     * @param mixed $data        проверяемые данные
     * @param bool  $expectArray ожидаем массив?
     * @param mixed $error       куда писать ошибку
     * @return bool
     */
    public function checkDataType(&$data, $expectArray, &$error)
    {
        $isArray = is_array($data);
        if ($expectArray && !$isArray) {
            $this->isValid = false;
            $error = App::t('По контракту ожидаем массив данных. Получили :type.', [':type' => gettype($data)]);
            return false;
        } elseif (!$expectArray && $isArray) {
            $this->isValid = false;
            $error = App::t('Данные в массиве. В контракте не заявлено.');
            return false;
        }
        return true;
    }

    /**
     * Типовой валидатор "filter_var".
     *
     * По заданному описанию вызывает php::filter_var(). Описание:
     * <pre>
     * $desc = [
     *     'filter' => mixed,    // по правилам filter_var(). Обязательный элемент.
     *     'options'  => array,  // по правилам filter_var(). По ситуации.
     *     'message'  => string, // свое сообщение об ошибке. Необязательно.
     * ];
     * </pre>
     *
     * @param array $desc  описание валидатора
     * @param mixed $data  проверяемые данные
     * @param mixed $value куда писать валидное значение
     * @param mixed $error куда писать ошибку
     * @return void
     * @throws \LogicException
     */
    public function validatorFilter_var(&$desc, &$data, &$value, &$error)
    {
        if (!isset($desc['filter'])) {
            throw new \LogicException('Не задан обязательный параметр "filter"');
        }
        $filter = $desc['filter'];
        $options = isset($desc['options']) ? ($desc['options']) : null;

        $result = filter_var($data, $filter, ['options' => $options]);
        if ($result === false) {
            $this->isValid = false;
            $value = null;
            $message = isset($desc['message']) ? $desc['message'] : 'Ошибка валидации поля';
            $message = [App::t($message)];
            $error = is_array($error) ? array_merge($error, $message) : $message;
        } else {
            $value = $result;
        }
    }

    /**
     * Типовой валидатор "external".
     *
     * По заданному описанию вызывает внешний валидатор через php::call_user_func(). Описание:
     * <pre>
     * $desc = [
     *     'function' => mixed,  // по правилам call_user_func(). Обязательный элемент.
     *     'options'  => array,  // доп.параметры для передачи во внешний валидатор. Необязательно.
     *     'message'  => string, // свое сообщение об ошибке. Необязательно.
     * ];
     * </pre>
     *
     * Требования к внешнему валидатору:
     * <pre>
     * function someValidator(mixed $value, [array $options]) : ['errors' => mixed] | ['value' => mixed]
     * </pre>
     *
     * @param array $desc  описание валидатора
     * @param mixed $data  проверяемые данные
     * @param mixed $value куда писать валидное значение
     * @param mixed $error куда писать ошибку
     * @return void
     * @throws \LogicException
     */
    public function validatorExternal(&$desc, &$data, &$value, &$error)
    {
        if (!isset($desc['function'])) {
            throw new \LogicException('Не задан обязательный параметр "function"');
        }
        $function = $desc['function'];
        $options = isset($desc['options']) ? ($desc['options']) : null;

        $result = call_user_func($function, $data, $options);

        if (isset($result['errors'])) {
            $this->isValid = false;
            $value = null;

            $message = isset($desc['message']) ? App::t($desc['message']) : $result['errors'];
            if (!is_array($message)) {
                $message = [$message];
            }

            $error = is_array($error) ? array_merge($error, $message) : $message;
        } elseif (isset($result['value'])) {
            $value = $result['value'];
        } else {
            if (is_array($function)) {
                $function = implode('::', $function);
            }
            throw new \LogicException("Внешний валидатор {$function}() вернул неопознанный ответ");
        }
    }

    /**
     * Типовой валидатор "length".
     *
     * Проверка длины строки. Описание:
     * <pre>
     * $desc = [
     *     'min'     => int,
     *     'max'     => int,
     *     'message' => string, // свое сообщение об ошибке. Необязательно.
     * ];
     * </pre>
     *
     * Любой из параметров можно пропустить. Значение '0' - неограниченная длина.
     *
     * Проверяемое значение может быть равно NULL, только если до этого были ошибки в других валидаторах. При этом
     * выходим без дополнительных сообщений.
     *
     * @param array  $desc  описание валидатора
     * @param string $data  проверяемые данные
     * @param mixed  $value куда писать валидное значение
     * @param mixed  $error куда писать ошибку
     * @return void
     * @throws \LogicException
     */
    public function validatorLength(&$desc, &$data, &$value, &$error)
    {
        if (is_null($data)) {
            $this->isValid = false;
            return;
        }

        // Предохранитель для разработчика
        if (!is_string($data)) {
            throw new \LogicException('Проверка длины строки не применима к текущему типу данных: '
                . gettype($value));
        }

        $min = isset($desc['min']) ? (int)$desc['min'] : 0;
        $max = isset($desc['max']) ? (int)$desc['max'] : 0;
        $message = isset($desc['message']) ? App::t($desc['message']) : '';

        $len = mb_strlen(strval($data));

        if ($min && $len < $min) {
            $errMsg[] = $message ?: App::t('Слишком короткое значение, минимум M символов', ['M' => $min]);
        } elseif ($max && $len > $max) {
            $errMsg[] = $message ?: App::t('Слишком длинное значение, максимум M символов', ['M' => $max]);
        } else {
            $errMsg = null;
        }

        if ($errMsg) {
            $this->isValid = false;
            $value = null;
            $error = is_array($error) ? array_merge($error, $errMsg) : $errMsg;
        }
    }

    /**
     * Типовой валидатор "bounds".
     *
     * Проверка рационального числа в заданных пределах. Полезно для проверки чисел с плавающей точкой, в дополнение
     * к filter_var(FILTER_VALIDATE_FLOAT). Описание:
     * <pre>
     * $desc = [
     *     'min'     => number,
     *     'max'     => number,
     *     'message' => string, // свое сообщение об ошибке. Необязательно.
     * ];
     * </pre>
     *
     * Любой из параметров можно пропустить. Значение 'NULL' - не проверять границу с этой стороны.
     *
     * Проверяемое значение может быть равно NULL, только если до этого были ошибки в других валидаторах. При этом
     * выходим без дополнительных сообщений.
     *
     * @param array  $desc  описание валидатора
     * @param number $data  проверяемые данные
     * @param mixed  $value куда писать валидное значение
     * @param mixed  $error куда писать ошибку
     * @return void
     * @throws \LogicException
     */
    public function validatorBounds(&$desc, &$data, &$value, &$error)
    {
        if (is_null($data)) {
            $this->isValid = false;
            return;
        }

        // Предохранитель для разработчика
        if (!is_numeric($data)) {
            throw new \LogicException('Проверка границ числа не применима к такому типу данных: ' . gettype($value));
        }

        $min = isset($desc['min']) ? (float)$desc['min'] : null;
        $max = isset($desc['max']) ? (float)$desc['max'] : null;
        $message = isset($desc['message']) ? App::t($desc['message']) : '';
        $data *= 1; // приведение к типу

        if (!is_null($min) && $data < $min) {
            $errMsg[] = $message ?: App::t('Значение меньше допустимого, минимум M', ['M' => $min]);
        } elseif (!is_null($min) && $data > $max) {
            $errMsg[] = $message ?: App::t('Значение больше допустимого, максимум M', ['M' => $max]);
        } else {
            $errMsg = null;
        }

        if ($errMsg) {
            $this->isValid = false;
            $value = null;
            $error = is_array($error) ? array_merge($error, $errMsg) : $errMsg;
        }
    }

    /**
     * Результат проведенной валидации. Геттер.
     * @return bool
     */
    public function isValid()
    {
        return $this->isValid;
    }
}
