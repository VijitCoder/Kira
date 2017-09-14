<?php
namespace kira\web;

use kira\core\App;
use kira\exceptions\FormException;

/**
 * Служебный класс для модели формы. Содержит только методы валидации. Готовые данные и ошибки передаются в клиентский
 * класс и тут не хранятся.
 *
 * См. документацию, "Модель формы"
 *
 * @internal
 */
class FormValidator
{
    /**
     * Флаг результата валидации, успешно или нет
     * @var bool
     */
    protected $isValid = true;

    /**
     * Предохранитель. Если будет вызван типовой валидатор, неизвестный классу, пробросим исключение. Это должно помочь
     * разработчику в поиске ошибки.
     * @param $method
     * @param $params
     * @throws FormException
     */
    public function __call($method, $params)
    {
        if (strncmp($method, 'validator', 8) === 0) {
            throw new FormException('Неизвестный типовой валидатор: ' . $method . PHP_EOL
                . 'Проверьте правильность контракта формы.');
        }
    }

    /**
     * Валидация одного узла дерева контракта. Рекурсия.
     *
     * Функция не предполагает прямой вызов из кода приложения. Это часть логики движка.
     *
     * Узел может быть равен нулю, только если он конечный и в контракте заявлено принять поле "как есть". Тогда пишем
     * полученные данные без валидации и выходим.
     *
     * Если узел не конечный (является веткой), тогда в нем не может быть валидаторов, есть только дочерний массив
     * узлов. Для каждого такого узла рекурсивно вызываем этот же метод.
     *
     * @param array|null $contractPart описательная часть контракта по конкретному узлу
     * @param mixed      $data         проверяемые данные. Соответствуют узлу в массиве {@see Form::$rawData}
     * @param mixed      $value        куда писать валидированное значение. Соответствует узлу в  {@see Form::$values}
     * @param mixed      $error        куда писать ошибку. Соответствует узлу в  {@see Form::$errors}
     * @throws FormException через магический FormValidator::__call()
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

        if ($expectArray = isset($contractPart['validators']['expect_array'])) {
            $expectArray = $contractPart['validators']['expect_array'] == true;
            unset($contractPart['validators']['expect_array']);
        }
        if (!$this->checkDataTypeForArray($data, $expectArray, $error)) {
            return;
        }

        $validators = &$contractPart['validators'];
        $this->popupRequired($validators);

        if ($expectArray) {
            foreach ($data as $k => $d) {
                $this->fireValidators($validators, $d, $value[$k], $error[$k]);
            }
        } else {
            $this->fireValidators($validators, $data, $value, $error);
        }
    }

    /**
     * Проверяем данные на соответствие ожиданиям
     *
     * Есть две ситуации: ждем массив или любой другой тип данных. Если ожидания не оправдались - ошибка валидации.
     *
     * Прим: это особый вид валидатора, поэтому его вызов отличается от остальных Функций validator*()
     *
     * @param mixed $data        проверяемые данные
     * @param bool  $expectArray ожидаем массив?
     * @param mixed $error       куда писать ошибку
     * @return bool
     */
    private function checkDataTypeForArray(&$data, $expectArray, &$error)
    {
        $isArray = is_array($data);
        if ($expectArray && !$isArray) {
            $this->isValid = false;
            $error[] = App::t('По контракту ожидаем массив данных. Получили :type.', [':type' => gettype($data)]);
            return false;
        } elseif (!$expectArray && $isArray) {
            $this->isValid = false;
            $error[] = App::t('Данные в массиве. В контракте не заявлено.');
            return false;
        }
        return true;
    }

    /**
     * Ставим валидатор "required" на первое место
     *
     * Валидатор "required" имеет особое значение: соответствующий ему метод так же проверяет, можно ли в принципе
     * пропустить  проверки, если поле пустое. Т.о. вызов валидатора "required" необходим в любом случае. Чтобы
     * не перегружать логику основного метода валидации, делаем так, чтобы "required" всегда присутствовал в списке
     * валидаторов. Если он не описан в контракте, тогда просто ставим его в FALSE.
     *
     * @param array $validators
     */
    private function popupRequired(&$validators)
    {
        if (isset($validators['required'])) {
            if (key($validators) !== 'required') {
                $tmp = ['required' => $validators['required']];
                unset($validators['required']);
                $validators = array_merge($tmp, $validators);
                unset($tmp);
            }
        } else {
            $validators = array_merge(['required' => false], $validators);
        }
    }

    /**
     * Последовательный вызов всех заявленных валидаторов для конкретного значения
     *
     * Каждый последующий валидатор должен работать с данными, обработанными предыдущим. Если какой-то валидатор
     * забракует значение, прерываем проверки.
     *
     * @param array $validators валидаторы данных
     * @param mixed $data       проверяемые данные
     * @param mixed $value      куда писать значение
     * @param mixed $error      куда писать ошибку
     */
    private function fireValidators(&$validators, &$data, &$value, &$error)
    {
        $newData = $data;
        foreach ($validators as $type => $description) {
            $method = 'validator' . ucfirst($type);
            $passed = $this->$method($description, $newData, $value, $error);
            if (!$passed) {
                break;
            }
            $newData = $value;
        }
    }

    /**
     * Типовой валидатор "required"
     *
     * Валидатор с особым поведением. Он вызывается всегда, т.к. в нем выясняем, можно ли пропустить проверки.
     *
     * Если поле пустое, нет смысла его валидировать. При этом нужно проверить, задана ли в контакте его обязательность.
     * Внутри функции пишем значение поля, возможно клиенту важно, какая именно пустота (строка, массив и т.д.).
     * Пишем ошибку валидатора "required", если она есть.
     *
     * Прим: разделение логики этого метода повлечет за собой сильное усложнение основного метода валидации.
     *
     * @param array|bool $required настройки валидатора "required"
     * @param mixed      $data     проверяемые данные
     * @param mixed      $value    куда писать значение
     * @param mixed      $error    куда писать ошибку
     * @return bool
     */
    protected function validatorRequired(&$required, &$data, &$value, &$error)
    {
        $passed = !empty($data);

        if (!$passed) {
            $value = $data;

            if ($required) {
                $this->isValid = false;
                $error[] = isset($required['message'])
                    ? $required['message']
                    : App::t('Поле должно быть заполнено');
            }
        } else {
            $value = $data;
        }

        return $passed;
    }

    /**
     * Типовой валидатор "filter_var"
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
     * @return bool
     * @throws FormException
     */
    protected function validatorFilter_var(&$desc, &$data, &$value, &$error)
    {
        if (!isset($desc['filter'])) {
            throw new FormException('Не задан обязательный параметр "filter"');
        }
        $filter = $desc['filter'];
        $options = $desc['options'] ?? null;
        $flags = $desc['flags'] ?? null;

        $passed = filter_var($data, $filter, compact('options', 'flags'));

        if ($passed === false) {
            $this->isValid = false;
            $message = isset($desc['message']) ? $desc['message'] : 'Ошибка валидации поля';
            $message = [App::t($message)];
            $error = is_array($error) ? array_merge($error, $message) : $message;
        } else {
            $value = $passed;
        }

        return $passed;
    }

    /**
     * Типовой валидатор "external"
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
     * function someValidator(mixed $value, [array $options]) : ['error' => mixed] | ['value' => mixed]
     * </pre>
     *
     * @param array $desc  описание валидатора
     * @param mixed $data  проверяемые данные
     * @param mixed $value куда писать валидное значение
     * @param mixed $error куда писать ошибку
     * @return bool
     * @throws FormException
     * @throws FormException
     */
    protected function validatorExternal(&$desc, &$data, &$value, &$error)
    {
        if (!isset($desc['function'])) {
            throw new FormException('Не задан обязательный параметр "function"');
        }
        $function = $desc['function'];
        $options = isset($desc['options']) ? ($desc['options']) : [];

        $result = call_user_func($function, $data, $options);

        if (isset($result['error'])) {
            $this->isValid = false;
            $passed = false;

            $message = isset($desc['message']) ? App::t($desc['message']) : $result['error'];
            if (!is_array($message)) {
                $message = [$message];
            }

            $error = is_array($error) ? array_merge($error, $message) : $message;
        } elseif (isset($result['value'])) {
            $passed = true;
            $value = $result['value'];
        } else {
            if (is_array($function)) {
                $function = implode('::', $function);
            }
            throw new FormException("Внешний валидатор {$function}() вернул неопознанный ответ");
        }

        return $passed;
    }

    /**
     * Типовой валидатор "length"
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
     * @return bool
     * @throws FormException
     */
    protected function validatorLength(&$desc, &$data, &$value, &$error)
    {
        if (!is_string($data)) {
            throw new FormException('Проверка длины строки не применима к текущему типу данных: '
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

        if (!($passed = !$errMsg)) {
            $this->isValid = false;
            $error = is_array($error) ? array_merge($error, $errMsg) : $errMsg;
        }

        return $passed;
    }

    /**
     * Типовой валидатор "bounds"
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
     * @return bool
     * @throws FormException
     */
    protected function validatorBounds(&$desc, &$data, &$value, &$error)
    {
        if (is_null($data)) {
            $this->isValid = false;
            return false;
        }

        if (!is_numeric($data)) {
            throw new FormException('Проверка границ числа не применима к такому типу данных: ' . gettype($value));
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

        if (!($passed = !$errMsg)) {
            $this->isValid = false;
            $error = is_array($error) ? array_merge($error, $errMsg) : $errMsg;
        }

        return $passed;
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
